<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\PersonEconomicTypeAssignment;
use App\Modules\Advertising\Services\CampaignBudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Cagnotte par type économique (instruction explicite du fondateur,
 * 2026-07-31, confirmée par exemple concret Orange CI) : chaque type
 * économique dispose, par campagne, d'une cagnotte fixe dimensionnée en
 * pourcentage de la part utilisateur totale déjà financée — jamais un
 * facteur qui réduirait la part de chaque événement individuel. Un
 * spectateur touche la part utilisateur standard pleine tant que la
 * cagnotte de son type n'est pas épuisée, puis 0 au-delà (l'événement
 * reste accepté et tracé, Wasplex absorbe le reliquat).
 */
class EconomicTypePoolTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EconomicType::query()->update(['state' => 'retired']);
    }

    private function makeEconomicType(string $stableKey, int $userSharePercentage, bool $isDefault = false): EconomicType
    {
        return EconomicType::create([
            'stable_key' => $stableKey,
            'name' => ucfirst($stableKey),
            'version' => 1,
            'user_share_percentage' => $userSharePercentage,
            'monthly_quota' => null,
            'is_default' => $isDefault,
            'state' => ConfigurationState::Active,
        ]);
    }

    private function assignType(EconomicType $type, string $personId): void
    {
        PersonEconomicTypeAssignment::create([
            'person_id' => $personId,
            'economic_type_id' => $type->id,
        ]);
    }

    public function test_a_viewer_receives_the_full_standard_share_regardless_of_percentage_while_the_pool_has_room(): void
    {
        $this->makeEconomicType('default-test', 100, isDefault: true);
        $gold = $this->makeEconomicType('gold', 30);

        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $viewer = $this->makeBeneficiary();
        $this->assignType($gold, $viewer->person_id);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $viewer,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($event);

        // La part standard d'un événement à 1000f est 500f (50/50 exact) —
        // le type Gold à 30% ne la réduit PAS : il définit seulement la
        // taille de sa cagnotte de campagne, jamais un facteur par
        // événement.
        $this->assertSame(500, $accepted->user_share_amount);
        $this->assertFalse($accepted->economic_type_pool_exhausted);
        $this->assertSame($gold->id, $accepted->economic_type_id);
    }

    public function test_the_pool_exhausts_after_enough_events_and_further_events_still_get_accepted_with_zero_share(): void
    {
        $this->makeEconomicType('default-test', 100, isDefault: true);
        $gold = $this->makeEconomicType('gold', 30);

        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $viewer = $this->makeBeneficiary();
        $this->assignType($gold, $viewer->person_id);

        // Part utilisateur totale de la campagne : 5000f (50% de 10000f).
        // Cagnotte Gold (30%) : 1500f. Chaque événement de 1000f verse
        // 500f — la cagnotte tient exactement 3 événements (1500f) avant
        // épuisement.
        $shares = [];
        for ($i = 0; $i < 4; $i++) {
            $event = $this->budgetService()->submitQualifiedEvent(
                campaign: $campaign,
                version: $version,
                beneficiary: $viewer,
                format: 'banner',
                evidence: ['proof' => 'completion'],
                appliedPriceAmount: 1_000,
                idempotencyKey: (string) Str::uuid(),
                correlationId: (string) Str::uuid(),
            );

            $accepted = $this->budgetService()->acceptQualifiedEvent($event);
            $shares[] = [$accepted->user_share_amount, $accepted->economic_type_pool_exhausted];
        }

        $this->assertSame([
            [500, false],
            [500, false],
            [500, false],
            [0, true],
        ], $shares);
    }

    public function test_value_conservation_holds_even_once_the_pool_is_exhausted(): void
    {
        $this->makeEconomicType('default-test', 100, isDefault: true);
        $gold = $this->makeEconomicType('gold', 30);

        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $viewer = $this->makeBeneficiary();
        $this->assignType($gold, $viewer->person_id);

        for ($i = 0; $i < 3; $i++) {
            $event = $this->budgetService()->submitQualifiedEvent(
                campaign: $campaign,
                version: $version,
                beneficiary: $viewer,
                format: 'banner',
                evidence: ['proof' => 'completion'],
                appliedPriceAmount: 1_000,
                idempotencyKey: (string) Str::uuid(),
                correlationId: (string) Str::uuid(),
            );
            $this->budgetService()->acceptQualifiedEvent($event);
        }

        $exhaustedEvent = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $viewer,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($exhaustedEvent);

        $this->assertSame(0, $accepted->user_share_amount);
        $this->assertTrue($accepted->economic_type_pool_exhausted);

        // Wasplex absorbe l'intégralité du reliquat non versé — aucune
        // fuite d'arrondi, la valeur de l'événement reste conservée.
        $this->assertDatabaseHas('ledger.postings', [
            'ledger_transaction_id' => $accepted->distribution_transaction_id,
            'amount' => 1_000,
            'direction' => 'credit',
        ]);
    }

    public function test_two_economic_types_have_independent_pools_on_the_same_campaign(): void
    {
        $this->makeEconomicType('default-test', 100, isDefault: true);
        $gold = $this->makeEconomicType('gold', 30);
        $silver = $this->makeEconomicType('silver', 20);

        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $goldViewer = $this->makeBeneficiary();
        $this->assignType($gold, $goldViewer->person_id);
        $silverViewer = $this->makeBeneficiary();
        $this->assignType($silver, $silverViewer->person_id);

        // Épuise entièrement la cagnotte Gold (1500f, 3 x 500f).
        for ($i = 0; $i < 3; $i++) {
            $event = $this->budgetService()->submitQualifiedEvent(
                campaign: $campaign,
                version: $version,
                beneficiary: $goldViewer,
                format: 'banner',
                evidence: ['proof' => 'completion'],
                appliedPriceAmount: 1_000,
                idempotencyKey: (string) Str::uuid(),
                correlationId: (string) Str::uuid(),
            );
            $this->budgetService()->acceptQualifiedEvent($event);
        }

        // La cagnotte Silver (20% de 5000f = 1000f) n'a jamais été
        // touchée : un spectateur Silver touche toujours sa part pleine,
        // insensible à l'épuisement de la cagnotte Gold.
        $silverEvent = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $silverViewer,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $accepted = $this->budgetService()->acceptQualifiedEvent($silverEvent);

        $this->assertSame(500, $accepted->user_share_amount);
        $this->assertFalse($accepted->economic_type_pool_exhausted);
    }

    public function test_preview_reflects_pool_exhaustion_honestly(): void
    {
        $this->makeEconomicType('default-test', 100, isDefault: true);
        $gold = $this->makeEconomicType('gold', 30);

        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);

        $viewer = $this->makeBeneficiary();
        $this->assignType($gold, $viewer->person_id);

        $service = app(CampaignBudgetService::class);

        $this->assertSame(
            500,
            $service->previewUserShareForPerson(1_000, $viewer->person_id, $viewer->id, $campaign),
        );

        for ($i = 0; $i < 3; $i++) {
            $event = $this->budgetService()->submitQualifiedEvent(
                campaign: $campaign,
                version: $version,
                beneficiary: $viewer,
                format: 'banner',
                evidence: ['proof' => 'completion'],
                appliedPriceAmount: 1_000,
                idempotencyKey: (string) Str::uuid(),
                correlationId: (string) Str::uuid(),
            );
            $this->budgetService()->acceptQualifiedEvent($event);
        }

        $this->assertSame(
            0,
            $service->previewUserShareForPerson(1_000, $viewer->person_id, $viewer->id, $campaign),
        );
    }
}
