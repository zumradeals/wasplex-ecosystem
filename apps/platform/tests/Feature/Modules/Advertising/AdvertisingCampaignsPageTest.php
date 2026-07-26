<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Campagnes (UX-0001 §8) : table complète des campagnes du dossier
 * annonceur. Même protocole que {@see AdvertisingOverviewPageTest}.
 */
class AdvertisingCampaignsPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/campaigns');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_with_no_advertiser_profile_sees_the_onboarding_state(): void
    {
        $user = $this->makeUser('no-profile-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/advertising/campaigns');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/campaigns')
            ->where('access.allowed', true)
            ->where('advertiserProfile', null)
            ->where('campaigns', []),
        );
    }

    public function test_a_representative_sees_only_its_own_campaigns_with_budget_and_event_counts(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 5_000);

        // Une autre annonceuse ne doit jamais apparaître dans cette liste.
        $this->makeCampaign($this->makeAdvertiserProfile());

        $response = $this->actingAs($user)->get('/advertising/campaigns');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/campaigns')
            ->where('access.allowed', true)
            ->has('campaigns', 1)
            ->where('campaigns.0.id', $campaign->id)
            ->where('campaigns.0.budget.available', 5_000)
            ->where('campaigns.0.events.pending', 0)
            ->where('campaigns.0.events.accepted', 0)
            ->where('campaigns.0.events.rejected', 0),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.campaigns.index'));
    }
}
