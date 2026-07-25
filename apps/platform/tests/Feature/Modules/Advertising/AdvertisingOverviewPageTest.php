<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Tableau de bord annonceur (P007-W1, `A-001-01`/`A-002-01`). Contrairement
 * à `CampaignSubmissionRouteTest` (API JSON), cette route vit dans le
 * groupe `auth`/`verified` : un visiteur non authentifié est redirigé vers
 * la connexion, jamais un 401 JSON — même protocole que
 * `WalletOverviewPageTest`.
 */
class AdvertisingOverviewPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_a_grant_sees_the_denied_state_not_a_crash(): void
    {
        $user = $this->makeUser('no-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/advertising');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/overview')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_active_grant')
            ->where('advertiserProfile', null)
            ->where('campaigns', []),
        );
    }

    public function test_a_subject_with_a_grant_but_no_advertiser_profile_sees_the_onboarding_state(): void
    {
        $user = $this->makeUser('no-profile-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/advertising');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/overview')
            ->where('access.allowed', true)
            ->where('advertiserProfile', null)
            ->where('campaigns', []),
        );
    }

    public function test_a_representative_sees_only_its_own_campaigns_with_projected_budget(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 5_000);

        // Une autre annonceuse ne doit jamais apparaître dans cette liste.
        $this->makeCampaign($this->makeAdvertiserProfile());

        $response = $this->actingAs($user)->get('/advertising');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/overview')
            ->where('access.allowed', true)
            ->where('advertiserProfile.legal_name', $advertiser->legal_name)
            ->has('campaigns', 1)
            ->where('campaigns.0.id', $campaign->id)
            ->where('campaigns.0.state', 'active')
            ->where('campaigns.0.budget.available', 5_000)
            ->where('campaigns.0.budget.reserved', 0)
            ->where('campaigns.0.budget.consumed', 0),
        );
    }

    public function test_the_advertising_overview_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("'advertising'", $webRoutes);
        $this->assertTrue(Route::has('advertising.overview'));
    }
}
