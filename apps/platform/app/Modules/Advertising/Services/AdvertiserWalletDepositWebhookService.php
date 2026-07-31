<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\AdvertiserWalletDepositState;
use App\Modules\Advertising\Models\AdvertiserWalletDeposit;
use App\Modules\Advertising\Models\AdvertiserWalletDepositWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traite une entrée d'inbox déjà enregistrée pour le dépôt Wallet annonceur
 * (instruction explicite du fondateur, 2026-07-31 ; mirroir exact de
 * {@see CampaignFundingWebhookService}). Idempotent par construction : un
 * dépôt déjà `completed`/`failed` (état terminal) court-circuite tout
 * retraitement.
 *
 * La branche succès réutilise directement
 * {@see AdvertiserWalletService::deposit()} plutôt que de dupliquer la
 * logique comptable — `$deposit->id` sert de clé d'idempotence Ledger.
 */
class AdvertiserWalletDepositWebhookService
{
    private const HANDLED_EVENTS = ['payment.success', 'payment.failed', 'payment.cancelled'];

    public function __construct(
        private readonly AdvertiserWalletService $walletService,
    ) {}

    public function process(AdvertiserWalletDepositWebhookEvent $event): void
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
            ? AdvertiserWalletDeposit::query()->where('provider_reference', $reference)->first()
            : null;

        if ($deposit === null) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'advertiser_wallet_deposit_not_found'])->save();

            return;
        }

        $event->forceFill(['advertiser_wallet_deposit_id' => $deposit->id])->save();

        if (in_array($deposit->state, [AdvertiserWalletDepositState::Completed, AdvertiserWalletDepositState::Failed], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'advertiser_wallet_deposit_already_terminal'])->save();

            return;
        }

        if (! in_array($deposit->state, [AdvertiserWalletDepositState::AwaitingProvider, AdvertiserWalletDepositState::Pending, AdvertiserWalletDepositState::UnknownReconciliation], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'advertiser_wallet_deposit_not_awaiting_confirmation'])->save();

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
    private function handleSuccess(AdvertiserWalletDeposit $deposit, array $payload, AdvertiserWalletDepositWebhookEvent $event): void
    {
        $confirmedAmount = $payload['data']['transaction']['amount'] ?? null;

        if (! is_int($confirmedAmount) || $confirmedAmount !== $deposit->amount) {
            // Écart entre le montant attendu et le montant confirmé : jamais
            // présenté comme un succès (même garantie que le dépôt Wallet
            // personnel et le financement de campagne).
            $deposit->forceFill(['state' => AdvertiserWalletDepositState::UnknownReconciliation])->save();
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'amount_mismatch'])->save();

            return;
        }

        $fees = isset($payload['data']['transaction']['fees']) ? (int) $payload['data']['transaction']['fees'] : ($deposit->fees_amount ?? 0);
        $netAmount = isset($payload['data']['transaction']['net_amount']) ? (int) $payload['data']['transaction']['net_amount'] : ($deposit->net_amount ?? ($deposit->amount - $fees));

        DB::transaction(function () use ($deposit, $fees, $netAmount, $event): void {
            $transaction = $this->walletService->deposit(
                $deposit->advertiserProfile,
                $deposit->currency,
                $deposit->amount,
                $deposit->id,
                (string) Str::uuid(),
            );

            $deposit->forceFill([
                'fees_amount' => $fees,
                'net_amount' => $netAmount,
                'ledger_transaction_id' => $transaction->id,
                'state' => AdvertiserWalletDepositState::Completed,
            ])->save();

            $event->forceFill(['processed_at' => now(), 'processing_result' => 'credited'])->save();
        });
    }

    private function handleFailure(AdvertiserWalletDeposit $deposit, string $eventType, AdvertiserWalletDepositWebhookEvent $event): void
    {
        $deposit->forceFill([
            'state' => AdvertiserWalletDepositState::Failed,
            'failure_reason' => $eventType,
        ])->save();

        $event->forceFill(['processed_at' => now(), 'processing_result' => 'marked_failed'])->save();
    }
}
