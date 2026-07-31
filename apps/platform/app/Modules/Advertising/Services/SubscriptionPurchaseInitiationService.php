<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\SubscriptionPurchaseState;
use App\Modules\Advertising\Models\SubscriptionPlan;
use App\Modules\Advertising\Models\SubscriptionPurchase;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Support\Facades\DB;

/**
 * Initie un achat d'abonnement en libre-service (instruction explicite du
 * fondateur, 2026-07-31) : mirroir exact de
 * {@see AdvertiserWalletDepositInitiationService}.
 */
class SubscriptionPurchaseInitiationService
{
    public function __construct(
        private readonly GeniusPayClient $geniusPay,
    ) {}

    public function initiate(
        string $purchaseId,
        string $personId,
        SubscriptionPlan $plan,
        string $initiatedByPersonAccountLinkId,
        string $successUrl,
        string $errorUrl,
        string $idempotencyKey,
    ): SubscriptionPurchase {
        $existing = SubscriptionPurchase::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $purchase = SubscriptionPurchase::create([
            'id' => $purchaseId,
            'person_id' => $personId,
            'subscription_plan_id' => $plan->id,
            'initiated_by_person_account_link_id' => $initiatedByPersonAccountLinkId,
            'state' => SubscriptionPurchaseState::Draft,
            'currency' => $plan->currency,
            'amount' => $plan->price_amount,
            'provider' => 'geniuspay',
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $intent = $this->geniusPay->createCheckoutPayment(new GeniusPayPaymentRequest(
                amount: $plan->price_amount,
                currency: $plan->currency,
                description: "Abonnement Wasplex — {$plan->name}",
                successUrl: $successUrl,
                errorUrl: $errorUrl,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (GeniusPayRequestFailedException $exception) {
            throw $exception;
        }

        return DB::transaction(function () use ($purchase, $intent): SubscriptionPurchase {
            $purchase->forceFill([
                'provider_payment_id' => $intent->providerPaymentId,
                'provider_reference' => $intent->reference,
                'checkout_url' => $intent->checkoutUrl,
                'fees_amount' => $intent->fees,
                'net_amount' => $intent->netAmount,
                'state' => SubscriptionPurchaseState::AwaitingProvider,
            ])->save();

            $purchase->forceFill(['state' => SubscriptionPurchaseState::Pending])->save();

            return $purchase->fresh();
        });
    }
}
