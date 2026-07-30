<?php

namespace App\Modules\Wallet\Deposit\Services\GeniusPay;

/**
 * Vérifie la signature HMAC-SHA256 d'un webhook GeniusPay
 * (`X-GeniusPay-Signature`, documentation prestataire « Sécurité des
 * webhooks ») sur le corps brut, avant toute désérialisation. Utilise
 * `hash_equals()` (comparaison à temps constant), jamais `===`.
 */
final class GeniusPayWebhookSignatureVerifier
{
    public function __construct(
        private readonly string $webhookSecret,
    ) {}

    public function verify(string $rawPayload, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }
}
