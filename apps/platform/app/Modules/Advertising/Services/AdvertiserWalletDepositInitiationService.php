<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\AdvertiserWalletDepositState;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\AdvertiserWalletDeposit;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Support\Facades\DB;

/**
 * Initie un dépôt Wallet annonceur en libre-service (instruction explicite
 * du fondateur, 2026-07-31) : mirroir exact de
 * {@see CampaignFundingInitiationService} — crée l'intention côté Wasplex
 * (`draft`), demande un paiement checkout à GeniusPay, puis fait avancer
 * l'état jusqu'à `pending` — jamais jusqu'à `completed`, qui n'existe qu'après
 * confirmation externe signée
 * ({@see AdvertiserWalletDepositWebhookService}).
 */
class AdvertiserWalletDepositInitiationService
{
    /** Montant minimum documenté par GeniusPay (`POST /payments`). */
    private const MINIMUM_AMOUNT = 200;

    public function __construct(
        private readonly GeniusPayClient $geniusPay,
    ) {}

    /**
     * `$depositId` est généré par l'appelant
     * (`AdvertiserWalletDepositInitiationController`) avant cet appel : les
     * URL de retour GeniusPay référencent ce dépôt précis.
     */
    public function initiate(
        string $depositId,
        AdvertiserProfile $advertiser,
        string $currency,
        string $initiatedByPersonAccountLinkId,
        int $amount,
        string $successUrl,
        string $errorUrl,
        string $idempotencyKey,
    ): AdvertiserWalletDeposit {
        if ($amount < self::MINIMUM_AMOUNT) {
            throw new \InvalidArgumentException(
                "le montant minimum d'un dépôt est de ".self::MINIMUM_AMOUNT.' '.$currency
            );
        }

        $existing = AdvertiserWalletDeposit::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $deposit = AdvertiserWalletDeposit::create([
            'id' => $depositId,
            'advertiser_profile_id' => $advertiser->id,
            'initiated_by_person_account_link_id' => $initiatedByPersonAccountLinkId,
            'state' => AdvertiserWalletDepositState::Draft,
            'currency' => $currency,
            'amount' => $amount,
            'provider' => 'geniuspay',
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $intent = $this->geniusPay->createCheckoutPayment(new GeniusPayPaymentRequest(
                amount: $amount,
                currency: $currency,
                description: "Recharge du Wallet annonceur Wasplex ({$advertiser->legal_name})",
                successUrl: $successUrl,
                errorUrl: $errorUrl,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (GeniusPayRequestFailedException $exception) {
            // Le dépôt reste en `draft` : aucun `checkout_url` n'a été
            // obtenu, l'annonceur n'a pas pu être redirigé, aucune valeur
            // n'est engagée (panne externe, pas un échec confirmé).
            throw $exception;
        }

        return DB::transaction(function () use ($deposit, $intent): AdvertiserWalletDeposit {
            $deposit->forceFill([
                'provider_payment_id' => $intent->providerPaymentId,
                'provider_reference' => $intent->reference,
                'checkout_url' => $intent->checkoutUrl,
                'fees_amount' => $intent->fees,
                'net_amount' => $intent->netAmount,
                'state' => AdvertiserWalletDepositState::AwaitingProvider,
            ])->save();

            $deposit->forceFill(['state' => AdvertiserWalletDepositState::Pending])->save();

            return $deposit->fresh();
        });
    }
}
