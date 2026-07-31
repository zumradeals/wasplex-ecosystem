<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Dépôt en libre-service dans le solde annonceur mutualisé via GeniusPay
 * (instruction explicite du fondateur, 2026-07-31 ; `advertiser_wallet.deposit`).
 * Mirroir exact de {@see CampaignFundingInitiationRouteTest}.
 */
class AdvertiserWalletDepositInitiationRouteTest extends CampaignFundingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_receives_a_structured_401_not_a_redirect(): void
    {
        $response = $this->postJson('/advertising/wallet/deposits', [
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['decision']);
    }

    public function test_the_advertisers_own_representative_receives_a_checkout_url(): void
    {
        $representative = $this->makeRepresentative();
        $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postJson('/advertising/wallet/deposits', [
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $response->assertJson(['state' => 'pending']);
        $response->assertJsonStructure(['deposit_id', 'checkout_url']);
        $this->assertDatabaseHas('advertising.advertiser_wallet_deposits', [
            'currency' => 'XOF',
            'amount' => 15000,
            'state' => 'pending',
        ]);
    }

    public function test_a_subject_without_an_advertiser_profile_receives_a_safe_404(): void
    {
        $stranger = $this->makeRepresentative();

        $response = $this->actingAs($stranger->user)->postJson('/advertising/wallet/deposits', [
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('advertising.advertiser_wallet_deposits', 0);
    }

    public function test_an_amount_below_the_minimum_is_rejected_by_validation(): void
    {
        $representative = $this->makeRepresentative();
        $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postJson('/advertising/wallet/deposits', [
            'amount' => 100,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
    }

    public function test_a_provider_outage_returns_a_service_unavailable_response(): void
    {
        $representative = $this->makeRepresentative();
        $this->makeAdvertiserProfile($representative);
        $this->geniusPay->shouldFail = true;

        $response = $this->actingAs($representative->user)->postJson('/advertising/wallet/deposits', [
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(503);
        $response->assertJson(['reason' => 'payment_provider_unavailable']);
    }

    public function test_replaying_the_same_idempotency_key_returns_the_same_deposit(): void
    {
        $representative = $this->makeRepresentative();
        $this->makeAdvertiserProfile($representative);
        $idempotencyKey = (string) Str::uuid();

        $first = $this->actingAs($representative->user)->postJson('/advertising/wallet/deposits', [
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => $idempotencyKey,
        ]);
        $second = $this->actingAs($representative->user)->postJson('/advertising/wallet/deposits', [
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => $idempotencyKey,
        ]);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('deposit_id'), $second->json('deposit_id'));
        $this->assertSame(1, DB::table('advertising.advertiser_wallet_deposits')->count());
    }

    public function test_the_advertiser_wallet_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('advertising.wallet'));
        $this->assertTrue(Route::has('advertising.wallet.deposits.store'));
        $this->assertTrue(Route::has('advertising.wallet.deposits.return'));
        $this->assertTrue(Route::has('advertising.wallet.allocations.store'));
    }
}
