<?php

namespace App\Modules\Wallet\Deposit;

use App\Modules\Wallet\Deposit\Console\Commands\ProcessDepositWebhooks;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Services\GeniusPay\GeniusPayCredentialsResolver;
use App\Modules\Wallet\Deposit\Services\GeniusPay\GeniusPayWebhookSignatureVerifier;
use App\Modules\Wallet\Deposit\Services\GeniusPay\HttpGeniusPayClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Frontière du sous-module Wallet/Deposit (AMD-0017 ; ecosystem/wallet/05).
 * Seul point de câblage entre le contrat {@see GeniusPayClient} et
 * l'implémentation HTTP réelle : aucun secret codé en dur, tout provient de
 * {@see GeniusPayCredentialsResolver} (ligne admin chiffrée si configurée,
 * sinon `config('services.geniuspay')` — véto du dirigeant 2026-07-30,
 * TD-0008-A).
 */
class WalletDepositServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GeniusPayClient::class, function (): HttpGeniusPayClient {
            $config = $this->app->make(GeniusPayCredentialsResolver::class)->resolve();

            return new HttpGeniusPayClient(
                http: $this->app->make(HttpFactory::class),
                baseUrl: $config['base_url'],
                apiKey: $config['api_key'],
                apiSecret: $config['api_secret'],
            );
        });

        $this->app->bind(GeniusPayWebhookSignatureVerifier::class, function (): GeniusPayWebhookSignatureVerifier {
            $config = $this->app->make(GeniusPayCredentialsResolver::class)->resolve();

            return new GeniusPayWebhookSignatureVerifier(
                webhookSecret: $config['webhook_secret'],
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessDepositWebhooks::class,
            ]);
        }
    }
}
