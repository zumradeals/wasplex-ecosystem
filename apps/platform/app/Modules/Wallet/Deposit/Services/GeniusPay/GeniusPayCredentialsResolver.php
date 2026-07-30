<?php

namespace App\Modules\Wallet\Deposit\Services\GeniusPay;

use App\Modules\Wallet\Deposit\Models\ProviderCredential;

/**
 * Source unique de vérité pour les identifiants GeniusPay effectivement
 * utilisés (véto du dirigeant, 2026-07-30) : la ligne admin
 * `ledger.wallet_deposit_provider_credentials` prévaut quand elle porte une
 * valeur ; à défaut, retombe sur `config('services.geniuspay')`
 * (`.env`, TD-0008-A) — jamais les deux mélangés champ par champ dans le
 * sens inverse, pour qu'un administrateur qui vient de configurer une seule
 * clé ne se retrouve jamais avec un mélange silencieux ancien/nouveau sur
 * les autres champs qu'il n'a pas encore renseignés.
 */
final class GeniusPayCredentialsResolver
{
    /**
     * @return array{base_url: string, api_key: string, api_secret: string, webhook_secret: string}
     */
    public function resolve(): array
    {
        $stored = $this->current();
        $env = config('services.geniuspay');

        return [
            'base_url' => $this->firstNonEmpty($stored?->base_url, $env['base_url'] ?? null) ?? '',
            'api_key' => $this->firstNonEmpty($stored?->api_key, $env['api_key'] ?? null) ?? '',
            'api_secret' => $this->firstNonEmpty($stored?->api_secret, $env['api_secret'] ?? null) ?? '',
            'webhook_secret' => $this->firstNonEmpty($stored?->webhook_secret, $env['webhook_secret'] ?? null) ?? '',
        ];
    }

    public function current(): ?ProviderCredential
    {
        return ProviderCredential::query()->where('provider', 'geniuspay')->first();
    }

    private function firstNonEmpty(?string $primary, ?string $fallback): ?string
    {
        return $primary !== null && $primary !== '' ? $primary : $fallback;
    }
}
