<?php

namespace App\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Support\Facades\DB;

/**
 * Initie un dépôt (AMD-0017 ; ecosystem/wallet/05 §2) : crée l'intention
 * côté Wasplex (`draft`), demande un paiement checkout à GeniusPay, puis
 * fait avancer l'état jusqu'à `pending` — jamais jusqu'à `completed`,
 * qui n'existe qu'après confirmation externe signée
 * ({@see DepositWebhookService}).
 *
 * Pilote strictement borné (AMD-0017 article 1) : pays et prestataire sont
 * fixés en dur ici, pas administrables — une extension exige une nouvelle
 * décision explicite, jamais une extrapolation de ce service.
 */
class DepositInitiationService
{
    private const PILOT_COUNTRY = 'CI';

    private const PILOT_CURRENCY = 'XOF';

    private const PILOT_PROVIDER = 'geniuspay';

    /** Montant minimum documenté par GeniusPay (`POST /payments`). */
    private const MINIMUM_AMOUNT = 200;

    public function __construct(
        private readonly GeniusPayClient $geniusPay,
    ) {}

    /**
     * `$depositId` est généré par l'appelant (`DepositInitiationController`)
     * avant cet appel : les URL de retour GeniusPay référencent ce dépôt
     * précis (`route('wallet.deposits.return', $depositId)`), donc son
     * identifiant doit exister avant même l'appel au prestataire.
     */
    public function initiate(
        string $depositId,
        string $personId,
        string $initiatedByPersonAccountLinkId,
        int $amount,
        string $successUrl,
        string $errorUrl,
        string $idempotencyKey,
    ): Deposit {
        if ($amount < self::MINIMUM_AMOUNT) {
            throw new \InvalidArgumentException(
                "le montant minimum d'un dépôt est de ".self::MINIMUM_AMOUNT.' '.self::PILOT_CURRENCY
            );
        }

        $existing = Deposit::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $deposit = Deposit::create([
            'id' => $depositId,
            'person_id' => $personId,
            'initiated_by_person_account_link_id' => $initiatedByPersonAccountLinkId,
            'state' => DepositState::Draft,
            'country_code' => self::PILOT_COUNTRY,
            'currency' => self::PILOT_CURRENCY,
            'amount' => $amount,
            'provider' => self::PILOT_PROVIDER,
            'idempotency_key' => $idempotencyKey,
        ]);

        try {
            $intent = $this->geniusPay->createCheckoutPayment(new GeniusPayPaymentRequest(
                amount: $amount,
                currency: self::PILOT_CURRENCY,
                description: 'Recharge Wallet Wasplex',
                successUrl: $successUrl,
                errorUrl: $errorUrl,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (GeniusPayRequestFailedException $exception) {
            // Le dépôt reste en `draft` : aucun `checkout_url` n'a été
            // obtenu, la personne n'a pas pu être redirigée, aucune valeur
            // n'est engagée (ADR-0007 §20 — panne externe, pas un échec de
            // dépôt confirmé).
            throw $exception;
        }

        return DB::transaction(function () use ($deposit, $intent): Deposit {
            $deposit->forceFill([
                'provider_payment_id' => $intent->providerPaymentId,
                'provider_reference' => $intent->reference,
                'checkout_url' => $intent->checkoutUrl,
                'fees_amount' => $intent->fees,
                'net_amount' => $intent->netAmount,
                'state' => DepositState::AwaitingProvider,
            ])->save();

            $deposit->forceFill(['state' => DepositState::Pending])->save();

            return $deposit->fresh();
        });
    }
}
