<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

class DepositInitiationRouteTest extends WalletDepositTestCase
{
    use RefreshDatabase;

    public function test_a_guest_receives_a_structured_401_not_a_redirect(): void
    {
        $response = $this->postJson('/wallet/deposits', [
            'amount' => 15000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['decision']);
    }

    public function test_an_authenticated_user_receives_a_checkout_url(): void
    {
        $user = $this->makeUser('depositor-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson('/wallet/deposits', [
            'amount' => 15000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $response->assertJson(['state' => 'pending']);
        $response->assertJsonStructure(['deposit_id', 'checkout_url']);
    }

    public function test_an_amount_below_the_minimum_is_rejected_by_validation(): void
    {
        $user = $this->makeUser('depositor-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson('/wallet/deposits', [
            'amount' => 100,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
    }

    public function test_a_provider_outage_returns_a_service_unavailable_response(): void
    {
        $user = $this->makeUser('depositor-'.Str::uuid().'@example.com');
        $this->geniusPay->shouldFail = true;

        $response = $this->actingAs($user)->postJson('/wallet/deposits', [
            'amount' => 15000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(503);
        $response->assertJson(['reason' => 'payment_provider_unavailable']);
    }
}
