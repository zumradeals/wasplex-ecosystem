<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use App\Modules\Wallet\Deposit\Services\GeniusPay\HttpGeniusPayClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Modules\Wallet\Deposit\Support\FakeGeniusPayClient;
use Tests\TestCase;

/**
 * Vérifie que l'adaptateur HTTP réel (jamais exercé par les autres tests,
 * qui utilisent {@see FakeGeniusPayClient})
 * envoie les en-têtes attendus et interprète correctement la forme de
 * réponse documentée par GeniusPay (`POST /payments`, réponse 201).
 */
class HttpGeniusPayClientTest extends TestCase
{
    private function client(): HttpGeniusPayClient
    {
        return new HttpGeniusPayClient(
            http: $this->app->make(HttpFactory::class),
            baseUrl: 'http://pay.genius.ci/api/v1/merchant',
            apiKey: 'pk_sandbox_test',
            apiSecret: 'sk_sandbox_test',
        );
    }

    public function test_it_parses_a_successful_response_into_a_normalized_payment_intent(): void
    {
        Http::fake([
            'pay.genius.ci/api/v1/merchant/payments' => Http::response([
                'success' => true,
                'data' => [
                    'id' => 456,
                    'reference' => 'MTX-A1B2C3D4E5',
                    'amount' => 15000,
                    'fees' => 450,
                    'net_amount' => 14550,
                    'status' => 'pending',
                    'checkout_url' => 'https://pay.genius.ci/checkout/abc',
                    'gateway' => 'wave',
                    'environment' => 'sandbox',
                ],
            ], 201),
        ]);

        $intent = $this->client()->createCheckoutPayment(new GeniusPayPaymentRequest(
            amount: 15000,
            currency: 'XOF',
            description: 'Recharge Wallet Wasplex',
            successUrl: 'https://wasplex.test/wallet/deposits/x/return',
            errorUrl: 'https://wasplex.test/wallet/deposits/x/return',
            idempotencyKey: 'idem-1',
        ));

        $this->assertSame('MTX-A1B2C3D4E5', $intent->reference);
        $this->assertSame(15000, $intent->amount);
        $this->assertSame(450, $intent->fees);
        $this->assertSame(14550, $intent->netAmount);
        $this->assertSame('https://pay.genius.ci/checkout/abc', $intent->checkoutUrl);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-API-Key', 'pk_sandbox_test')
                && $request->hasHeader('X-API-Secret', 'sk_sandbox_test')
                && $request['amount'] === 15000
                && ! isset($request['payment_method']);
        });
    }

    public function test_a_401_error_response_raises_a_domain_exception(): void
    {
        Http::fake([
            'pay.genius.ci/api/v1/merchant/payments' => Http::response([
                'success' => false,
                'error' => ['code' => 'INVALID_API_KEY'],
            ], 401),
        ]);

        $this->expectException(GeniusPayRequestFailedException::class);

        $this->client()->createCheckoutPayment(new GeniusPayPaymentRequest(
            amount: 15000,
            currency: 'XOF',
            description: 'Recharge Wallet Wasplex',
            successUrl: 'https://wasplex.test/return',
            errorUrl: 'https://wasplex.test/return',
            idempotencyKey: 'idem-2',
        ));
    }

    public function test_a_malformed_response_raises_a_domain_exception_rather_than_a_type_error(): void
    {
        Http::fake([
            'pay.genius.ci/api/v1/merchant/payments' => Http::response(['success' => true, 'data' => []], 201),
        ]);

        $this->expectException(GeniusPayRequestFailedException::class);

        $this->client()->createCheckoutPayment(new GeniusPayPaymentRequest(
            amount: 15000,
            currency: 'XOF',
            description: 'Recharge Wallet Wasplex',
            successUrl: 'https://wasplex.test/return',
            errorUrl: 'https://wasplex.test/return',
            idempotencyKey: 'idem-3',
        ));
    }
}
