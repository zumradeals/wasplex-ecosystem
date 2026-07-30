<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Deposit\Models\ProviderCredential;
use App\Modules\Wallet\Deposit\Services\GeniusPay\GeniusPayCredentialsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Véto du dirigeant 2026-07-30 (TD-0008-A) : la ligne admin
 * `ledger.wallet_deposit_provider_credentials` prévaut sur `.env` quand elle
 * porte une valeur ; sinon, `config('services.geniuspay')` reste la source
 * (voir `WalletDepositServiceProvider`, qui consomme ce resolver).
 */
class GeniusPayCredentialsResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_falls_back_to_env_config_when_no_row_exists(): void
    {
        config(['services.geniuspay' => [
            'base_url' => 'http://pay.genius.ci/api/v1/merchant',
            'api_key' => 'env-key',
            'api_secret' => 'env-secret',
            'webhook_secret' => 'env-webhook-secret',
        ]]);

        $resolved = app(GeniusPayCredentialsResolver::class)->resolve();

        $this->assertSame('env-key', $resolved['api_key']);
        $this->assertSame('env-secret', $resolved['api_secret']);
        $this->assertSame('env-webhook-secret', $resolved['webhook_secret']);
    }

    public function test_a_stored_row_takes_precedence_over_env_config(): void
    {
        config(['services.geniuspay' => [
            'base_url' => 'http://pay.genius.ci/api/v1/merchant',
            'api_key' => 'env-key',
            'api_secret' => 'env-secret',
            'webhook_secret' => 'env-webhook-secret',
        ]]);

        ProviderCredential::create([
            'provider' => 'geniuspay',
            'base_url' => 'https://admin-configured.example/api',
            'api_key' => 'admin-key',
            'api_secret' => 'admin-secret',
            'webhook_secret' => 'admin-webhook-secret',
        ]);

        $resolved = app(GeniusPayCredentialsResolver::class)->resolve();

        $this->assertSame('https://admin-configured.example/api', $resolved['base_url']);
        $this->assertSame('admin-key', $resolved['api_key']);
        $this->assertSame('admin-secret', $resolved['api_secret']);
        $this->assertSame('admin-webhook-secret', $resolved['webhook_secret']);
    }

    public function test_a_field_left_empty_in_the_stored_row_falls_back_to_env_config(): void
    {
        config(['services.geniuspay' => [
            'base_url' => 'http://pay.genius.ci/api/v1/merchant',
            'api_key' => 'env-key',
            'api_secret' => 'env-secret',
            'webhook_secret' => 'env-webhook-secret',
        ]]);

        ProviderCredential::create([
            'provider' => 'geniuspay',
            'base_url' => 'https://admin-configured.example/api',
            'api_key' => null,
            'api_secret' => null,
            'webhook_secret' => null,
        ]);

        $resolved = app(GeniusPayCredentialsResolver::class)->resolve();

        $this->assertSame('env-key', $resolved['api_key']);
        $this->assertSame('env-secret', $resolved['api_secret']);
        $this->assertSame('env-webhook-secret', $resolved['webhook_secret']);
    }
}
