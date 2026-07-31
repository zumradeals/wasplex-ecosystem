<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\SubscriptionPurchaseState;
use App\Modules\Advertising\Models\SubscriptionPurchase;
use App\Modules\Advertising\Models\SubscriptionPurchaseWebhookEvent;
use Illuminate\Support\Facades\DB;

/**
 * Traite une entrée d'inbox déjà enregistrée pour l'achat d'abonnement
 * (instruction explicite du fondateur, 2026-07-31 ; mirroir exact de
 * {@see AdvertiserWalletDepositWebhookService}). La branche succès délègue
 * l'activation à {@see SubscriptionActivationService}.
 */
class SubscriptionPurchaseWebhookService
{
    private const HANDLED_EVENTS = ['payment.success', 'payment.failed', 'payment.cancelled'];

    public function __construct(
        private readonly SubscriptionActivationService $activationService,
    ) {}

    public function process(SubscriptionPurchaseWebhookEvent $event): void
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
        $purchase = is_string($reference)
            ? SubscriptionPurchase::query()->where('provider_reference', $reference)->first()
            : null;

        if ($purchase === null) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'subscription_purchase_not_found'])->save();

            return;
        }

        $event->forceFill(['subscription_purchase_id' => $purchase->id])->save();

        if (in_array($purchase->state, [SubscriptionPurchaseState::Completed, SubscriptionPurchaseState::Failed], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'subscription_purchase_already_terminal'])->save();

            return;
        }

        if (! in_array($purchase->state, [SubscriptionPurchaseState::AwaitingProvider, SubscriptionPurchaseState::Pending, SubscriptionPurchaseState::UnknownReconciliation], true)) {
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'subscription_purchase_not_awaiting_confirmation'])->save();

            return;
        }

        match ($eventType) {
            'payment.success' => $this->handleSuccess($purchase, $payload, $event),
            'payment.failed', 'payment.cancelled' => $this->handleFailure($purchase, $eventType, $event),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleSuccess(SubscriptionPurchase $purchase, array $payload, SubscriptionPurchaseWebhookEvent $event): void
    {
        $confirmedAmount = $payload['data']['transaction']['amount'] ?? null;

        if (! is_int($confirmedAmount) || $confirmedAmount !== $purchase->amount) {
            $purchase->forceFill(['state' => SubscriptionPurchaseState::UnknownReconciliation])->save();
            $event->forceFill(['processed_at' => now(), 'processing_result' => 'amount_mismatch'])->save();

            return;
        }

        $fees = isset($payload['data']['transaction']['fees']) ? (int) $payload['data']['transaction']['fees'] : ($purchase->fees_amount ?? 0);
        $netAmount = isset($payload['data']['transaction']['net_amount']) ? (int) $payload['data']['transaction']['net_amount'] : ($purchase->net_amount ?? ($purchase->amount - $fees));

        DB::transaction(function () use ($purchase, $fees, $netAmount, $event): void {
            $transaction = $this->activationService->activate($purchase);

            $purchase->forceFill([
                'fees_amount' => $fees,
                'net_amount' => $netAmount,
                'ledger_transaction_id' => $transaction->id,
                'state' => SubscriptionPurchaseState::Completed,
            ])->save();

            $event->forceFill(['processed_at' => now(), 'processing_result' => 'activated'])->save();
        });
    }

    private function handleFailure(SubscriptionPurchase $purchase, string $eventType, SubscriptionPurchaseWebhookEvent $event): void
    {
        $purchase->forceFill([
            'state' => SubscriptionPurchaseState::Failed,
            'failure_reason' => $eventType,
        ])->save();

        $event->forceFill(['processed_at' => now(), 'processing_result' => 'marked_failed'])->save();
    }
}
