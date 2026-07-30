<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Http;

use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

class DepositWebhookRouteTest extends WalletDepositTestCase
{
    use RefreshDatabase;

    private function postWebhook(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/webhooks/geniuspay', [], [], [], [
            'HTTP_X_GENIUSPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_a_valid_webhook_is_accepted_recorded_and_credits_the_wallet(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));
        $deposit = Deposit::create([
            'person_id' => $link->person_id,
            'initiated_by_person_account_link_id' => $link->id,
            'state' => DepositState::Pending,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-ROUTE1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-ROUTE1', 15000, fees: 450, netAmount: 14550);

        $response = $this->postWebhook($signed['body'], $signed['signature']);

        $response->assertStatus(200);

        $deposit->refresh();
        $this->assertSame(DepositState::Completed, $deposit->state);

        $this->assertDatabaseCount('ledger.wallet_deposit_webhook_events', 1);

        $balances = $this->app->make(PersonBalanceProjection::class)->forPerson($link->person_id);
        $xof = collect($balances)->firstWhere('currency', 'XOF');
        $this->assertSame(15000, $xof['available']);
    }

    public function test_a_forged_signature_is_rejected_and_recorded(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-FORGED1', 15000);

        $response = $this->postWebhook($signed['body'], 'not-the-real-signature');

        $response->assertStatus(401);

        $event = DepositWebhookEvent::query()->sole();
        $this->assertFalse($event->signature_valid);
        $this->assertSame('signature_invalid', $event->processing_result);
    }

    public function test_a_missing_signature_header_is_rejected(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-NOSIG1', 15000);

        $response = $this->call('POST', '/webhooks/geniuspay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $signed['body']);

        $response->assertStatus(401);
    }
}
