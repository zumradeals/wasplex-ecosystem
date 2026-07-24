<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * `01-cycle-creation-valeur.md` §4, invariants 1 et 3 : aucune facturation
 * (consommation du budget campagne) ni aucune rémunération (répartition
 * vers les droits utilisateur) sans preuve acceptée. Un QualifiedEvent
 * pending — dont la preuve n'a pas encore été acceptée — n'a produit
 * aucune des deux.
 */
class BillingRequiresAcceptedProofTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_no_consumption_or_distribution_transaction_exists_before_acceptance(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 2_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $this->assertNull($event->consumption_transaction_id);
        $this->assertNull($event->distribution_transaction_id);
        $this->assertDatabaseMissing('ledger.ledger_transactions', ['type' => 'advertising_campaign_consumption']);
        $this->assertDatabaseMissing('ledger.ledger_transactions', ['type' => 'advertising_campaign_distribution']);
    }

    public function test_acceptance_is_the_only_path_producing_billing_and_reward_effects(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 2_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($event);

        $this->assertNotNull($accepted->consumption_transaction_id);
        $this->assertNotNull($accepted->distribution_transaction_id);
        $this->assertDatabaseHas('ledger.ledger_transactions', ['type' => 'advertising_campaign_consumption']);
        $this->assertDatabaseHas('ledger.ledger_transactions', ['type' => 'advertising_campaign_distribution']);
    }
}
