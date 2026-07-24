<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * ADR-0010 §7, `01-cycle-creation-valeur.md` §4 invariant 5 : une même
 * preuve ne produit jamais deux facturations ni deux rémunérations.
 */
class QualifiedEventIdempotencyTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_resubmitting_the_same_idempotency_key_returns_the_existing_event_without_a_second_reservation(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $key = 'same-proof-'.Str::uuid();

        $first = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 2_000,
            idempotencyKey: $key, correlationId: (string) Str::uuid(),
        );

        $second = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 2_000,
            idempotencyKey: $key, correlationId: (string) Str::uuid(),
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('advertising.qualified_events', 1);
        // Financement + réservation, une seule fois chacune (la réservation
        // n'est jamais dupliquée par le second appel).
        $this->assertDatabaseCount('ledger.ledger_transactions', 2);
    }

    public function test_accepting_the_same_event_twice_never_produces_a_second_billing_or_reward_effect(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 2_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );

        $first = $this->budgetService()->acceptQualifiedEvent($event);
        $second = $this->budgetService()->acceptQualifiedEvent($event->fresh());

        $this->assertSame($first->consumption_transaction_id, $second->consumption_transaction_id);
        $this->assertSame($first->distribution_transaction_id, $second->distribution_transaction_id);
        // Financement + réservation + consommation + répartition, une seule
        // fois chacune.
        $this->assertDatabaseCount('ledger.ledger_transactions', 4);
    }

    public function test_rejecting_an_already_accepted_event_is_a_no_op(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 2_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($event);
        $result = $this->budgetService()->rejectQualifiedEvent($accepted, 'Trop tard');

        $this->assertSame(BillingStatus::Accepted, $result->billing_status);
        $this->assertNull($result->release_transaction_id);
    }
}
