<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\PrecautionaryMeasure;
use App\Modules\Advertising\Services\Exceptions\CampaignNotAcceptingReservationsException;
use App\Modules\Advertising\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * ADR-0010 §4 (dernière ligne), §7 : une campagne suspendue ne réserve
 * plus de nouveau budget, mais les réservations déjà engagées suivent
 * leur cycle jusqu'à résolution.
 */
class CampaignSuspensionTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_suspended_campaign_refuses_a_new_reservation(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $case = app(ModerationService::class)->openCase($campaign, 'Destination non déclarée', 'high');
        app(ModerationService::class)->applyPrecautionaryMeasure($case, PrecautionaryMeasure::CampaignSuspended);

        $this->expectException(CampaignNotAcceptingReservationsException::class);

        $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign->fresh(), version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );
    }

    public function test_an_already_engaged_reservation_still_reaches_validation_after_suspension(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );

        $case = app(ModerationService::class)->openCase($campaign, 'Signalement en cours d\'examen', 'medium');
        app(ModerationService::class)->applyPrecautionaryMeasure($case, PrecautionaryMeasure::CampaignSuspended);

        $accepted = $this->budgetService()->acceptQualifiedEvent($event->fresh());

        $this->assertSame(BillingStatus::Accepted, $accepted->billing_status);
    }

    public function test_an_already_engaged_reservation_can_still_be_rejected_after_suspension(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );

        $case = app(ModerationService::class)->openCase($campaign, 'Signalement en cours d\'examen', 'medium');
        app(ModerationService::class)->applyPrecautionaryMeasure($case, PrecautionaryMeasure::CampaignSuspended);

        $rejected = $this->budgetService()->rejectQualifiedEvent($event->fresh(), 'Destination invalide constatée');

        $this->assertSame(BillingStatus::Rejected, $rejected->billing_status);
    }
}
