<?php

namespace Tests\Feature\Modules\Advertising\Admin;

use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Finance et rapprochement (UX-0001 §8) : gouverné par `campaign.fund`,
 * même capacité que l'action de financement (voir
 * `ModerationOverviewRouteTest::test_a_holder_of_campaign_fund_...`).
 */
class AdminFinanceRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/finance');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/finance');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/finance')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_active_grant')
            ->where('campaigns', []),
        );
    }

    public function test_a_holder_of_campaign_fund_sees_system_wide_budget_and_distribution(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign.fund', 'advertising.campaign');

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
            appliedPriceAmount: 1_001,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
            fraudDecision: FraudDecision::None,
        );
        app(CampaignBudgetService::class)->acceptQualifiedEvent($event);

        $response = $this->actingAs($staff->user)->get('/admin/finance');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/finance')
            ->where('access.allowed', true)
            ->has('budgetTotals', 1)
            ->where('budgetTotals.0.currency', 'XOF')
            // L'acceptation répartit immédiatement le compte « consommé »
            // vers le bénéficiaire et Wasplex (distribution) : il ne reste
            // donc jamais rien dessus après acceptation — seul
            // `distributionTotals` (agrégé depuis QualifiedEvent) porte le
            // montant réellement réparti.
            ->where('budgetTotals.0.available', 10_000 - 1_001)
            ->where('budgetTotals.0.reserved', 0)
            ->where('budgetTotals.0.consumed', 0)
            ->has('distributionTotals', 1)
            ->where('distributionTotals.0.accepted_total', 1_001)
            // Décision du fondateur 2026-07-26 (ADR-0010) : l'unité
            // résiduelle sur un montant impair revient à l'utilisateur.
            ->where('distributionTotals.0.user_share', 501)
            ->where('distributionTotals.0.wasplex_share', 500)
            ->has('campaigns', 1)
            ->where('campaigns.0.campaign_id', $campaign->id)
            ->where('campaigns.0.advertiser_legal_name', $advertiser->legal_name),
        );
    }

    public function test_the_admin_finance_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.finance'));
    }
}
