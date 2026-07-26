<?php

namespace Tests\Feature\Modules\Advertising\Admin;

use App\Modules\Advertising\Enums\ModerationDecision;
use App\Modules\Advertising\Services\ModerationService;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Publicité et modération (UX-0001 §8) : historique complet, au-delà de
 * la seule file « à traiter » — gouverné par `campaign.approve` ou
 * `campaign.moderate`.
 */
class AdminAdvertisingModerationRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/advertising-moderation');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_either_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/advertising-moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/advertising-moderation')
            ->where('access.allowed', false)
            ->where('campaigns', [])
            ->where('moderationCases', []),
        );
    }

    public function test_a_holder_of_campaign_approve_sees_all_campaigns_and_case_history(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.approve', 'advertising.advertiser_profile');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $this->proposeAndApproveVersion($campaign);

        $service = app(ModerationService::class);
        $openCase = $service->openCase(campaign: $campaign, reason: 'signalement ouvert', severity: 'medium');
        // Écart temporel explicite : l'ordre par `created_at` décroissant
        // du contrôleur ne doit rien devoir à une coïncidence de
        // millisecondes entre deux créations immédiates.
        $this->travel(1)->seconds();
        $resolvedCase = $service->openCase(campaign: $campaign, reason: 'signalement résolu', severity: 'low');
        $service->recordDecision($resolvedCase, ModerationDecision::NoViolationFound);

        $response = $this->actingAs($staff->user)->get('/admin/advertising-moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/advertising-moderation')
            ->where('access.allowed', true)
            ->has('campaigns', 1)
            ->where('campaigns.0.campaign_id', $campaign->id)
            ->where('campaigns.0.latest_version_state', 'approved')
            ->has('moderationCases', 2)
            // Ordonné par created_at décroissant (contrôleur) : le dossier
            // résolu en second apparaît en premier.
            ->where('moderationCases.0.moderation_case_id', $resolvedCase->id)
            ->where('moderationCases.0.status', 'resolved')
            ->where('moderationCases.1.moderation_case_id', $openCase->id)
            ->where('moderationCases.1.status', 'open'),
        );
    }

    public function test_the_admin_advertising_moderation_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.advertising-moderation'));
    }
}
