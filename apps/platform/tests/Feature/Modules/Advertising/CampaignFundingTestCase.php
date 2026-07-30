<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Wallet\Deposit\Support\FakeGeniusPayClient;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

/**
 * Base partagée par les tests du financement de campagne en libre-service
 * (véto du dirigeant, 2026-07-30) : même câblage `FakeGeniusPayClient` que
 * {@see WalletDepositTestCase}
 * (réutilisé tel quel, aucun second double GeniusPay créé), combiné aux
 * fabriques Advertising d'{@see AdvertisingTestCase}.
 */
abstract class CampaignFundingTestCase extends AdvertisingTestCase
{
    protected const WEBHOOK_SECRET = 'test-webhook-secret';

    protected FakeGeniusPayClient $geniusPay;

    protected function setUp(): void
    {
        parent::setUp();

        $this->geniusPay = new FakeGeniusPayClient;
        $this->app->instance(GeniusPayClient::class, $this->geniusPay);

        config(['services.geniuspay.webhook_secret' => self::WEBHOOK_SECRET]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{body: string, signature: string}
     */
    protected function signedWebhookPayload(string $event, string $reference, int $amount, ?int $fees = null, ?int $netAmount = null, array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'transaction' => [
                    'id' => random_int(1000, 999999),
                    'reference' => $reference,
                    'amount' => $amount,
                    'fees' => $fees,
                    'net_amount' => $netAmount,
                    'status' => $event === 'payment.success' ? 'completed' : 'failed',
                ],
                'merchant' => ['id' => (string) Str::uuid(), 'name' => 'Wasplex'],
                'environment' => 'sandbox',
            ],
        ], $overrides);

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, self::WEBHOOK_SECRET);

        return ['body' => $body, 'signature' => $signature];
    }
}
