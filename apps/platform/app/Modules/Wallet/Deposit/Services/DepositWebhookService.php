<?php

namespace App\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Balance\Services\PersonLedgerAccounts;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Http\Controllers\DepositWebhookController;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traite une entrée d'inbox déjà enregistrée (ADR-0007 §11 : signature
 * vérifiée et payload durablement stocké avant tout effet — voir
 * {@see DepositWebhookController}).
 * Idempotent par construction : un dépôt déjà `completed`/`failed` (état
 * terminal) court-circuite tout retraitement, qu'il s'agisse d'un rejeu du
 * même événement ou d'une nouvelle entrée d'inbox pour le même paiement
 * (ecosystem/wallet/05 §4).
 */
class DepositWebhookService
{
    private const HANDLED_EVENTS = ['payment.success', 'payment.failed', 'payment.cancelled'];

    public function __construct(
        private readonly LedgerPoster $poster,
        private readonly WalletCoverageAccounts $coverageAccounts,
        private readonly PersonLedgerAccounts $personAccounts,
    ) {}

    public function process(DepositWebhookEvent $event): void
    {
        if ($event->processed_at !== null) {
            return;
        }

        if (! $event->signature_valid) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'signature_invalid'])->save();

            return;
        }

        $payload = json_decode($event->raw_payload, true, flags: JSON_THROW_ON_ERROR);
        $eventType = $payload['event'] ?? null;
        $event->forceFill(['event_type' => $eventType])->save();

        if (! is_string($eventType) || ! in_array($eventType, self::HANDLED_EVENTS, true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'ignored_event_type'])->save();

            return;
        }

        $reference = $payload['data']['transaction']['reference'] ?? null;
        $deposit = is_string($reference)
            ? Deposit::query()->where('provider_reference', $reference)->first()
            : null;

        if ($deposit === null) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'deposit_not_found'])->save();

            return;
        }

        $event->forceFill(['wallet_deposit_id' => $deposit->id])->save();

        if (in_array($deposit->state, [DepositState::Completed, DepositState::Failed], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'deposit_already_terminal'])->save();

            return;
        }

        if (! in_array($deposit->state, [DepositState::AwaitingProvider, DepositState::Pending, DepositState::UnknownReconciliation], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'deposit_not_awaiting_confirmation'])->save();

            return;
        }

        match ($eventType) {
            'payment.success' => $this->handleSuccess($deposit, $payload, $event),
            'payment.failed', 'payment.cancelled' => $this->handleFailure($deposit, $eventType, $event),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleSuccess(Deposit $deposit, array $payload, DepositWebhookEvent $event): void
    {
        $confirmedAmount = $payload['data']['transaction']['amount'] ?? null;

        if (! is_int($confirmedAmount) || $confirmedAmount !== $deposit->amount) {
            // Écart entre le montant attendu et le montant confirmé :
            // jamais présenté comme un succès (ecosystem/wallet/05 §3, §5).
            $deposit->forceFill(['state' => DepositState::UnknownReconciliation])->save();
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'amount_mismatch'])->save();

            return;
        }

        $fees = isset($payload['data']['transaction']['fees']) ? (int) $payload['data']['transaction']['fees'] : ($deposit->fees_amount ?? 0);
        $netAmount = isset($payload['data']['transaction']['net_amount']) ? (int) $payload['data']['transaction']['net_amount'] : ($deposit->net_amount ?? ($deposit->amount - $fees));

        DB::transaction(function () use ($deposit, $fees, $netAmount, $event): void {
            $coverage = $this->coverageAccounts->coverage($deposit->currency);
            $userRights = $this->personAccounts->available($deposit->person_id, $deposit->currency);

            $postings = [
                new PostingLine($coverage->id, PostingDirection::Debit, $netAmount, $deposit->currency, "Dépôt confirmé — couverture ({$deposit->provider_reference})"),
            ];

            if ($fees > 0) {
                $feesAccount = $this->coverageAccounts->feesExpense($deposit->currency);
                $postings[] = new PostingLine($feesAccount->id, PostingDirection::Debit, $fees, $deposit->currency, "Dépôt confirmé — frais GeniusPay ({$deposit->provider_reference})");
            }

            $postings[] = new PostingLine($userRights->id, PostingDirection::Credit, $deposit->amount, $deposit->currency, "Dépôt confirmé — WP crédité ({$deposit->provider_reference})");

            $transaction = $this->poster->post(new TransactionIntent(
                type: 'wallet_deposit',
                businessDate: now(),
                accountingDate: now(),
                sourceModule: 'wallet_deposit',
                sourceReference: $deposit->id,
                idempotencyScope: 'wallet.deposit',
                idempotencyKey: $deposit->id,
                correlationId: (string) Str::uuid(),
                authoredBy: 'system:geniuspay-webhook',
                postings: $postings,
                evidenceReference: $deposit->provider_reference,
            ));

            $deposit->forceFill([
                'fees_amount' => $fees,
                'net_amount' => $netAmount,
                'ledger_transaction_id' => $transaction->id,
                'state' => DepositState::Completed,
            ])->save();

            $event->forceFill(['processed_at' => now(), 'processing_result' => 'credited'])->save();
        });
    }

    private function handleFailure(Deposit $deposit, string $eventType, DepositWebhookEvent $event): void
    {
        $deposit->forceFill([
            'state' => DepositState::Failed,
            'failure_reason' => $eventType,
        ])->save();

        $event->forceFill(['processed_at' => now(), 'processing_result' => 'marked_failed'])->save();
    }
}
