<?php

namespace App\Modules\Wallet\Deposit\Contracts\GeniusPay;

/**
 * Intention de paiement à transmettre à GeniusPay (`POST /payments`, mode
 * checkout — aucun `payment_method` fourni).
 */
final readonly class GeniusPayPaymentRequest
{
    public function __construct(
        public int $amount,
        public string $currency,
        public string $description,
        public string $successUrl,
        public string $errorUrl,
        public string $idempotencyKey,
    ) {}
}
