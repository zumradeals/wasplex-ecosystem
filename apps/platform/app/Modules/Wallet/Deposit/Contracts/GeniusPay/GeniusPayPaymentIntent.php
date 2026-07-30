<?php

namespace App\Modules\Wallet\Deposit\Contracts\GeniusPay;

/**
 * Réponse normalisée de GeniusPay à la création d'un paiement (réponse
 * 201, `data.*` de la documentation prestataire). `fees`/`net_amount`
 * peuvent être absents à ce stade (connus seulement à la confirmation) :
 * jamais devinés ici.
 */
final readonly class GeniusPayPaymentIntent
{
    public function __construct(
        public string $providerPaymentId,
        public string $reference,
        public int $amount,
        public ?int $fees,
        public ?int $netAmount,
        public string $status,
        public string $checkoutUrl,
        public string $environment,
    ) {}
}
