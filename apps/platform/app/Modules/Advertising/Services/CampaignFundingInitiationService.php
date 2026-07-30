<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\CampaignFundingState;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignFunding;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Services\DepositInitiationService;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Support\Facades\DB;

/**
 * Initie un financement de campagne en libre-service (véto du dirigeant,
 * 2026-07-30) : mirroir exact de
 * {@see DepositInitiationService} —
 * crée l'intention côté Wasplex (`draft`), demande un paiement checkout à
 * GeniusPay, puis fait avancer l'état jusqu'à `pending` — jamais jusqu'à
 * `completed`, qui n'existe qu'après confirmation externe signée
 * ({@see CampaignFundingWebhookService}).
 *
 * Réutilise le contrat {@see GeniusPayClient} du module Wallet/Deposit tel
 * quel (déjà générique, aucun couplage Wallet dans sa signature) plutôt que
 * de dupliquer un second adaptateur GeniusPay.
 */
class CampaignFundingInitiationService
{
    /** Montant minimum documenté par GeniusPay (`POST /payments`). */
    private const MINIMUM_AMOUNT = 200;

    public function __construct(
        private readonly GeniusPayClient $geniusPay,
    ) {}

    /**
     * `$campaignFundingId` est généré par l'appelant
     * (`CampaignFundingInitiationController`) avant cet appel : les URL de
     * retour GeniusPay référencent ce financement précis.
     */
    public function initiate(
        string $campaignFundingId,
        Campaign $campaign,
        string $initiatedByPersonAccountLinkId,
        int $amount,
        string $successUrl,
        string $errorUrl,
        string $idempotencyKey,
    ): CampaignFunding {
        if ($amount < self::MINIMUM_AMOUNT) {
            throw new \InvalidArgumentException(
                "le montant minimum d'un financement est de ".self::MINIMUM_AMOUNT.' '.$campaign->currency
            );
        }

        $existing = CampaignFunding::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $funding = CampaignFunding::create([
            'id' => $campaignFundingId,
            'campaign_id' => $campaign->id,
            'initiated_by_person_account_link_id' => $initiatedByPersonAccountLinkId,
            'state' => CampaignFundingState::Draft,
            'currency' => $campaign->currency,
            'amount' => $amount,
            'provider' => 'geniuspay',
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $intent = $this->geniusPay->createCheckoutPayment(new GeniusPayPaymentRequest(
                amount: $amount,
                currency: $campaign->currency,
                description: "Financement de campagne Wasplex ({$campaign->code})",
                successUrl: $successUrl,
                errorUrl: $errorUrl,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (GeniusPayRequestFailedException $exception) {
            // Le financement reste en `draft` : aucun `checkout_url` n'a été
            // obtenu, l'annonceur n'a pas pu être redirigé, aucune valeur
            // n'est engagée (panne externe, pas un échec confirmé).
            throw $exception;
        }

        return DB::transaction(function () use ($funding, $intent): CampaignFunding {
            $funding->forceFill([
                'provider_payment_id' => $intent->providerPaymentId,
                'provider_reference' => $intent->reference,
                'checkout_url' => $intent->checkoutUrl,
                'fees_amount' => $intent->fees,
                'net_amount' => $intent->netAmount,
                'state' => CampaignFundingState::AwaitingProvider,
            ])->save();

            $funding->forceFill(['state' => CampaignFundingState::Pending])->save();

            return $funding->fresh();
        });
    }
}
