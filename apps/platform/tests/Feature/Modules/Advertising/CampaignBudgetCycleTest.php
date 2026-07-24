<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Models\Posting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Câblage exact du cycle financier vers LedgerPoster (ADR-0010 §4) et
 * conservation de la valeur (§7).
 */
class CampaignBudgetCycleTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_funding_credits_the_available_budget_via_a_balanced_ledger_transaction(): void
    {
        $campaign = $this->makeCampaign();
        $projection = app(CampaignBudgetProjection::class);

        $this->fundCampaign($campaign, 10_000);

        $this->assertSame(10_000, $projection->available($campaign));
        $this->assertSame(0, $projection->reserved($campaign));
        $this->assertSame(0, $projection->consumed($campaign));
        $this->assertDatabaseHas('ledger.ledger_transactions', ['type' => 'advertising_campaign_funding']);
    }

    public function test_a_reservation_never_exceeds_the_available_budget(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 1_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $this->expectException(InsufficientBudgetException::class);

        $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_001,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_pending_control_moves_the_cost_from_available_to_reserved(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $projection = app(CampaignBudgetProjection::class);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 3_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $this->assertSame(7_000, $projection->available($campaign->fresh()));
        $this->assertSame(3_000, $projection->reserved($campaign->fresh()));
        $this->assertSame(BillingStatus::Pending, $event->billing_status);
        $this->assertInstanceOf(LedgerTransaction::class, $event->reservationTransaction);
    }

    public function test_validation_consumes_the_reservation_and_splits_the_net_distributable_fifty_fifty(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $projection = app(CampaignBudgetProjection::class);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 4_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($event);

        $this->assertSame(BillingStatus::Accepted, $accepted->billing_status);
        $this->assertSame(6_000, $projection->available($campaign->fresh()));
        $this->assertSame(0, $projection->reserved($campaign->fresh()));
        $this->assertSame(0, $projection->consumed($campaign->fresh()));

        $distributionPostings = Posting::query()
            ->where('ledger_transaction_id', $accepted->distribution_transaction_id)
            ->get();

        $credits = $distributionPostings->where('direction', PostingDirection::Credit);
        $this->assertCount(2, $credits);
        $this->assertSame(4_000, $credits->sum('amount'));
        $this->assertTrue($credits->every(fn ($posting) => $posting->amount >= 0));

        // Ratio 50/50 exact (AMD-0002) sur un montant pair.
        $this->assertEqualsCanonicalizing([2_000, 2_000], $credits->pluck('amount')->all());
    }

    public function test_the_fifty_fifty_split_sums_exactly_even_on_an_odd_amount(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_001);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 4_001,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($event);

        $credits = Posting::query()
            ->where('ledger_transaction_id', $accepted->distribution_transaction_id)
            ->where('direction', PostingDirection::Credit)
            ->get();

        $this->assertSame(4_001, $credits->sum('amount'));
    }

    public function test_a_rejected_reservation_releases_exactly_the_reserved_amount(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $projection = app(CampaignBudgetProjection::class);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 2_500,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $rejected = $this->budgetService()->rejectQualifiedEvent($event, 'Preuve invalide');

        $this->assertSame(BillingStatus::Rejected, $rejected->billing_status);
        $this->assertSame(10_000, $projection->available($campaign->fresh()));
        $this->assertSame(0, $projection->reserved($campaign->fresh()));

        // La libération est une contre-écriture explicite de la réservation
        // d'origine, jamais une transaction libre (ADR-0003 §11).
        $releaseTransaction = LedgerTransaction::find($rejected->release_transaction_id);
        $this->assertSame($event->reservation_transaction_id, $releaseTransaction->reverses_transaction_id);
        $this->assertNotNull($releaseTransaction->reversal_reason);
    }

    public function test_rejection_never_creates_a_billing_or_reward_effect(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_500,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $this->budgetService()->rejectQualifiedEvent($event, 'Preuve rejetée');

        $this->assertDatabaseMissing('ledger.ledger_transactions', ['type' => 'advertising_campaign_consumption']);
        $this->assertDatabaseMissing('ledger.ledger_transactions', ['type' => 'advertising_campaign_distribution']);
    }
}
