<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\AdvertiserWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Allocation du solde annonceur mutualisé vers une campagne précise
 * (instruction explicite du fondateur, 2026-07-31 ;
 * `advertiser_wallet.allocate`).
 */
class AdvertiserWalletAllocationRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_receives_a_structured_401_not_a_redirect(): void
    {
        $campaign = $this->makeCampaign();

        $response = $this->postJson('/advertising/wallet/allocations', [
            'campaign_id' => $campaign->id,
            'amount' => 1000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(401);
    }

    public function test_a_representative_can_allocate_from_their_own_wallet_to_their_own_campaign(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $campaign = $this->makeCampaign($advertiser, 'XOF');
        app(AdvertiserWalletService::class)->deposit($advertiser, 'XOF', 10000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $response = $this->actingAs($representative->user)->postJson('/advertising/wallet/allocations', [
            'campaign_id' => $campaign->id,
            'amount' => 4000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $response->assertJson(['campaign_available' => 4000]);
        $this->assertSame(4000, app(CampaignBudgetProjection::class)->available($campaign->fresh()));
    }

    public function test_a_representative_cannot_allocate_to_another_advertisers_campaign(): void
    {
        $representative = $this->makeRepresentative();
        $ownAdvertiser = $this->makeAdvertiserProfile($representative);
        app(AdvertiserWalletService::class)->deposit($ownAdvertiser, 'XOF', 10000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $strangerCampaign = $this->makeCampaign();

        $response = $this->actingAs($representative->user)->postJson('/advertising/wallet/allocations', [
            'campaign_id' => $strangerCampaign->id,
            'amount' => 1000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404);
        $this->assertSame(0, app(CampaignBudgetProjection::class)->available($strangerCampaign->fresh()));
    }

    public function test_allocating_more_than_the_available_wallet_balance_is_rejected(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $campaign = $this->makeCampaign($advertiser, 'XOF');
        app(AdvertiserWalletService::class)->deposit($advertiser, 'XOF', 1000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $response = $this->actingAs($representative->user)->postJson('/advertising/wallet/allocations', [
            'campaign_id' => $campaign->id,
            'amount' => 1001,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
        $response->assertJson(['reason' => 'insufficient_wallet_balance']);
        $this->assertSame(0, app(CampaignBudgetProjection::class)->available($campaign->fresh()));
    }

    public function test_a_subject_without_an_advertiser_profile_receives_a_safe_404(): void
    {
        $stranger = $this->makeRepresentative();
        $campaign = $this->makeCampaign();

        $response = $this->actingAs($stranger->user)->postJson('/advertising/wallet/allocations', [
            'campaign_id' => $campaign->id,
            'amount' => 1000,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404);
    }
}
