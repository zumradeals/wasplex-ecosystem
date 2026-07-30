<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\CampaignFundingState;
use App\Modules\Advertising\Models\CampaignFunding;
use App\Modules\Advertising\Models\CampaignFundingWebhookEvent;
use App\Modules\Wallet\Deposit\Services\DepositWebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traite une entrée d'inbox déjà enregistrée pour le financement de
 * campagne (véto du dirigeant, 2026-07-30 ; mirroir exact de
 * {@see DepositWebhookService}).
 * Idempotent par construction : un financement déjà `completed`/`failed`
 * (état terminal) court-circuite tout retraitement.
 *
 * La branche succès réutilise directement
 * {@see CampaignBudgetService::fund()} (déjà écrit et testé pour le flux
 * staff `campaign.fund`) plutôt que de dupliquer la logique comptable —
 * `$campaignFunding->id` sert de clé d'idempotence Ledger, ce qui exclut
 * toute collision avec les références humaines du flux staff existant.
 */
class CampaignFundingWebhookService
{
    private const HANDLED_EVENTS = ['payment.success', 'payment.failed', 'payment.cancelled'];

    public function __construct(
        private readonly CampaignBudgetService $budgetService,
    ) {}

    public function process(CampaignFundingWebhookEvent $event): void
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
        $funding = is_string($reference)
            ? CampaignFunding::query()->where('provider_reference', $reference)->first()
            : null;

        if ($funding === null) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'campaign_funding_not_found'])->save();

            return;
        }

        $event->forceFill(['campaign_funding_id' => $funding->id])->save();

        if (in_array($funding->state, [CampaignFundingState::Completed, CampaignFundingState::Failed], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'campaign_funding_already_terminal'])->save();

            return;
        }

        if (! in_array($funding->state, [CampaignFundingState::AwaitingProvider, CampaignFundingState::Pending, CampaignFundingState::UnknownReconciliation], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'campaign_funding_not_awaiting_confirmation'])->save();

            return;
        }

        match ($eventType) {
            'payment.success' => $this->handleSuccess($funding, $payload, $event),
            'payment.failed', 'payment.cancelled' => $this->handleFailure($funding, $eventType, $event),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleSuccess(CampaignFunding $funding, array $payload, CampaignFundingWebhookEvent $event): void
    {
        $confirmedAmount = $payload['data']['transaction']['amount'] ?? null;

        if (! is_int($confirmedAmount) || $confirmedAmount !== $funding->amount) {
            // Écart entre le montant attendu et le montant confirmé : jamais
            // présenté comme un succès (même garantie que le dépôt Wallet).
            $funding->forceFill(['state' => CampaignFundingState::UnknownReconciliation])->save();
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'amount_mismatch'])->save();

            return;
        }

        $fees = isset($payload['data']['transaction']['fees']) ? (int) $payload['data']['transaction']['fees'] : ($funding->fees_amount ?? 0);
        $netAmount = isset($payload['data']['transaction']['net_amount']) ? (int) $payload['data']['transaction']['net_amount'] : ($funding->net_amount ?? ($funding->amount - $fees));

        DB::transaction(function () use ($funding, $fees, $netAmount, $event): void {
            $transaction = $this->budgetService->fund(
                $funding->campaign,
                $funding->amount,
                $funding->id,
                (string) Str::uuid(),
            );

            $funding->forceFill([
                'fees_amount' => $fees,
                'net_amount' => $netAmount,
                'ledger_transaction_id' => $transaction->id,
                'state' => CampaignFundingState::Completed,
            ])->save();

            $event->forceFill(['processed_at' => now(), 'processing_result' => 'credited'])->save();
        });
    }

    private function handleFailure(CampaignFunding $funding, string $eventType, CampaignFundingWebhookEvent $event): void
    {
        $funding->forceFill([
            'state' => CampaignFundingState::Failed,
            'failure_reason' => $eventType,
        ])->save();

        $event->forceFill(['processed_at' => now(), 'processing_result' => 'marked_failed'])->save();
    }
}
