<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Financement de campagne en libre-service par l'annonceur via GeniusPay
 * (véto du dirigeant, 2026-07-30 ; `campaign.fund_self`). Mirroir de
 * `Tests\Feature\Modules\Wallet\Deposit\Http\DepositInitiationRouteTest`.
 */
class CampaignFundingInitiationRouteTest extends CampaignFundingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_receives_a_structured_401_not_a_redirect(): void
    {
        $campaign = $this->makeCampaign();

        $response = $this->postJson("/advertising/campaigns/{$campaign->id}/self-funding", [
            'amount' => 15000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['decision']);
    }

    public function test_the_advertisers_own_representative_receives_a_checkout_url(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        $response = $this->actingAs($representative->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/self-funding",
            ['amount' => 15000, 'idempotency_key' => (string) Str::uuid()],
        );

        $response->assertCreated();
        $response->assertJson(['state' => 'pending']);
        $response->assertJsonStructure(['campaign_funding_id', 'checkout_url']);
        $this->assertDatabaseHas('advertising.campaign_fundings', [
            'campaign_id' => $campaign->id,
            'state' => 'pending',
        ]);
    }

    public function test_a_subject_who_does_not_own_the_campaign_receives_a_safe_403(): void
    {
        $campaign = $this->makeCampaign();
        $stranger = $this->makeRepresentative();

        $response = $this->actingAs($stranger->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/self-funding",
            ['amount' => 15000, 'idempotency_key' => (string) Str::uuid()],
        );

        $response->assertStatus(403);
        $this->assertDatabaseCount('advertising.campaign_fundings', 0);
    }

    public function test_an_amount_below_the_minimum_is_rejected_by_validation(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        $response = $this->actingAs($representative->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/self-funding",
            ['amount' => 100, 'idempotency_key' => (string) Str::uuid()],
        );

        $response->assertStatus(422);
    }

    public function test_a_provider_outage_returns_a_service_unavailable_response(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));
        $this->geniusPay->shouldFail = true;

        $response = $this->actingAs($representative->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/self-funding",
            ['amount' => 15000, 'idempotency_key' => (string) Str::uuid()],
        );

        $response->assertStatus(503);
        $response->assertJson(['reason' => 'payment_provider_unavailable']);
    }

    public function test_replaying_the_same_idempotency_key_returns_the_same_funding(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));
        $idempotencyKey = (string) Str::uuid();

        $first = $this->actingAs($representative->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/self-funding",
            ['amount' => 15000, 'idempotency_key' => $idempotencyKey],
        );
        $second = $this->actingAs($representative->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/self-funding",
            ['amount' => 15000, 'idempotency_key' => $idempotencyKey],
        );

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('campaign_funding_id'), $second->json('campaign_funding_id'));
        $this->assertSame(1, DB::table('advertising.campaign_fundings')->count());
    }

    public function test_the_campaign_self_funding_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('advertising.campaigns.self-funding.store'));
        $this->assertTrue(Route::has('advertising.campaigns.self-funding.return'));
    }
}
