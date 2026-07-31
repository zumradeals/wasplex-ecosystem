<?php

namespace Tests\Feature\Modules\Advertising\Admin;

use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Enums\ModerationDecision;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Advertising\Services\ModerationService;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Fermeture de la boucle de gain (P0, demande Koné 2026-07-26) : la file
 * personnel Wasplex qui rend enfin actionnables `campaign.approve`,
 * `campaign.fund`, `event.accept`/`event.reject` — jusqu'ici backend-only,
 * sans aucun écran (voir ModerationOverviewController). Même protocole que
 * `AdvertisingOverviewPageTest`/`WalletOverviewPageTest` : vit dans le
 * groupe `auth`/`verified`, un visiteur non authentifié est redirigé vers
 * la connexion, jamais un 401 JSON.
 */
class ModerationOverviewRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/moderation');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_any_grant_sees_all_three_sections_denied(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation')
            ->where('campaignApproval.access.allowed', false)
            ->where('campaignApproval.access.reason', 'no_active_grant')
            ->where('campaignApproval.items', [])
            ->where('campaignFunding.access.allowed', false)
            ->where('campaignFunding.items', [])
            ->where('qualifiedEvents.access.allowed', false)
            ->where('qualifiedEvents.items', []),
        );
    }

    public function test_a_holder_of_campaign_approve_sees_only_that_section_populated(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.approve', 'advertising.advertiser_profile');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $versionService = app(CampaignVersionService::class);
        $version = $versionService->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Titre de test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $advertiser->representative,
        );
        $versionService->submitForReview($version);

        $response = $this->actingAs($staff->user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation')
            ->where('campaignApproval.access.allowed', true)
            ->has('campaignApproval.items', 1)
            ->where('campaignApproval.items.0.campaign_id', $campaign->id)
            ->where('campaignApproval.items.0.advertiser_legal_name', $advertiser->legal_name)
            ->where('campaignFunding.access.allowed', false)
            ->where('qualifiedEvents.access.allowed', false),
        );
    }

    public function test_a_holder_of_campaign_fund_sees_fundable_campaigns_with_projected_budget(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.fund', 'advertising.campaign');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Titre de test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $advertiser->representative,
        );
        app(CampaignVersionService::class)->submitForReview($version);
        $this->fundCampaign($campaign, 7_500);

        $response = $this->actingAs($staff->user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation')
            ->where('campaignFunding.access.allowed', true)
            ->has('campaignFunding.items', 1)
            ->where('campaignFunding.items.0.campaign_id', $campaign->id)
            ->where('campaignFunding.items.0.available', 7_500)
            ->where('campaignApproval.access.allowed', false),
        );
    }

    public function test_a_draft_campaign_never_submitted_is_not_fundable(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.fund', 'advertising.campaign');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 7_500);

        $response = $this->actingAs($staff->user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation')
            ->where('campaignFunding.access.allowed', true)
            ->where('campaignFunding.items', [])
            ->where('campaignApproval.access.allowed', false),
        );
    }

    public function test_a_holder_of_event_accept_sees_pending_qualified_events(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'event.accept', 'advertising.qualified_event');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $beneficiary = $this->makeBeneficiary();

        $event = app(CampaignBudgetService::class)->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $beneficiary,
            format: 'banner',
            evidence: ['condition' => 'completion', 'completed' => true],
            appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
            fraudDecision: FraudDecision::None,
        );

        $response = $this->actingAs($staff->user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation')
            ->where('qualifiedEvents.access.allowed', true)
            ->has('qualifiedEvents.items', 1)
            ->where('qualifiedEvents.items.0.qualified_event_id', $event->id)
            ->where('qualifiedEvents.items.0.reward_amount', 1_000)
            ->where('campaignApproval.access.allowed', false),
        );
    }

    public function test_a_holder_of_campaign_moderate_sees_open_moderation_cases(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.moderate', 'advertising.campaign');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $case = app(ModerationService::class)->openCase(
            campaign: $campaign,
            reason: 'contenu trompeur signalé',
            severity: 'high',
        );

        $response = $this->actingAs($staff->user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/moderation')
            ->where('moderationCases.access.allowed', true)
            ->has('moderationCases.items', 1)
            ->where('moderationCases.items.0.moderation_case_id', $case->id)
            ->where('moderationCases.items.0.campaign_id', $campaign->id)
            ->where('moderationCases.items.0.severity', 'high')
            ->where('campaignApproval.access.allowed', false),
        );
    }

    public function test_a_resolved_moderation_case_never_appears_in_the_open_queue(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.moderate', 'advertising.campaign');

        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $service = app(ModerationService::class);
        $case = $service->openCase(campaign: $campaign, reason: 'signalement', severity: 'low');
        $service->recordDecision($case, ModerationDecision::NoViolationFound);

        $response = $this->actingAs($staff->user)->get('/admin/moderation');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('moderationCases.access.allowed', true)
            ->where('moderationCases.items', []),
        );
    }

    public function test_the_admin_moderation_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('admin/moderation', $webRoutes);
        $this->assertTrue(Route::has('admin.moderation'));
    }
}
