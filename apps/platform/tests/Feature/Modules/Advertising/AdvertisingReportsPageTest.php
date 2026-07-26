<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Services\CampaignBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Rapports (UX-0001 §8 « résultats agrégés ») : décompte des
 * `QualifiedEvent` par campagne et par statut de facturation.
 */
class AdvertisingReportsPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/reports');

        $response->assertRedirect('/login');
    }

    public function test_a_representative_sees_accepted_and_pending_totals_for_its_own_campaign(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign, author: $link);

        $service = app(CampaignBudgetService::class);
        $beneficiary = $this->makeBeneficiary();

        $accepted = $service->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $beneficiary,
            format: 'banner',
            evidence: ['condition' => 'completion'],
            appliedPriceAmount: 300,
            idempotencyKey: 'evt-accepted-'.Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
        $service->acceptQualifiedEvent($accepted);

        $service->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $beneficiary,
            format: 'banner',
            evidence: ['condition' => 'completion'],
            appliedPriceAmount: 200,
            idempotencyKey: 'evt-pending-'.Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $response = $this->actingAs($user)->get('/advertising/reports');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/reports')
            ->has('campaignReports', 1)
            ->where('campaignReports.0.campaign_id', $campaign->id)
            ->where('campaignReports.0.accepted.event_count', 1)
            ->where('campaignReports.0.accepted.amount_total', 300)
            ->where('campaignReports.0.pending.event_count', 1)
            ->where('campaignReports.0.pending.amount_total', 200)
            ->where('campaignReports.0.rejected.event_count', 0),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.reports'));
    }
}
