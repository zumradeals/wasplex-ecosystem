<?php

namespace App\Modules\Wallet\Deposit\Contracts\GeniusPay;

use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;

/**
 * Frontière d'intégration GeniusPay (ADR-0007 §14) : Wasplex ne manipule
 * que ce modèle normalisé (intention, montant, frais, statut, référence),
 * jamais les détails propres au prestataire au-delà de cette interface.
 * Isole tout le reste du module d'un éventuel changement de prestataire
 * (AMD-0017 article 7).
 */
interface GeniusPayClient
{
    /**
     * Crée un paiement en mode checkout (sans `payment_method`, la
     * personne choisit son moyen sur la page hébergée GeniusPay —
     * approche recommandée par la documentation du prestataire).
     *
     * @throws GeniusPayRequestFailedException
     */
    public function createCheckoutPayment(GeniusPayPaymentRequest $request): GeniusPayPaymentIntent;
}
