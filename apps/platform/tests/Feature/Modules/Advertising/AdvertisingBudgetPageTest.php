<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Budget (UX-0001 §8) : ce que chaque campagne peut encore engager,
 * reconstruit depuis {@see CampaignBudgetProjection}.
 */
class AdvertisingBudgetPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/budget');

        $response->assertRedirect('/login');
    }

    public function test_a_representative_sees_its_own_campaign_budget_breakdown(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 7_500);

        $response = $this->actingAs($user)->get('/advertising/budget');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/budget')
            ->has('campaignBudgets', 1)
            ->where('campaignBudgets.0.campaign_id', $campaign->id)
            ->where('campaignBudgets.0.available', 7_500)
            ->where('campaignBudgets.0.reserved', 0)
            ->where('campaignBudgets.0.consumed', 0),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.budget'));
    }
}
