<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Facturation (UX-0001 §8) : état du financement, jamais un formulaire de
 * paiement en libre-service — `campaign.fund` reste réservé au personnel
 * finance Wasplex (voir `routes/web.php`,
 * `advertising.campaigns.funding.store`).
 */
class AdvertisingBillingPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/billing');

        $response->assertRedirect('/login');
    }

    public function test_a_representative_sees_its_own_campaign_funding_state(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 4_200);

        $response = $this->actingAs($user)->get('/advertising/billing');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/billing')
            ->has('campaignFunding', 1)
            ->where('campaignFunding.0.campaign_id', $campaign->id)
            ->where('campaignFunding.0.covered_total', 4_200)
            ->where('campaignFunding.0.billed_to_date', 0)
            ->where('campaignFunding.0.available', 4_200),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.billing'));
    }
}
