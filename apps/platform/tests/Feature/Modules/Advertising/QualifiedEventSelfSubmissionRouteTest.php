<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Http\Requests\StoreSelfQualifiedEventRequest;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Auto-soumission par le bénéficiaire de sa propre preuve d'attention
 * qualifiée (W2, `event.self_submit`) — ferme la dette documentée sur
 * `event.submit` (migration `2026_07_25_100009`, TD-0004). Depuis P007,
 * une inscription réelle émet automatiquement ce grant (`user.base`) :
 * voir `test_an_authenticated_subject_without_a_grant_receives_a_safe_403`
 * pour le seul moyen restant de constituer un sujet réellement sans grant.
 */
class QualifiedEventSelfSubmissionRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private int $pricingCounter = 0;

    private function activatedPricingDefinition(int $value): string
    {
        $stableKey = 'test.self_submit_pricing_'.(++$this->pricingCounter);

        $definition = Definition::create([
            'stable_key' => $stableKey,
            'version' => 1,
            'domain' => 'advertising',
            'level' => ConfigurationLevel::C2,
            'value_type' => ValueType::Integer,
            'unit' => 'smallest_currency_unit',
            'constraints' => ['minimum' => 0],
            'description' => 'Test.',
            'state' => DefinitionState::Active,
        ]);

        $author = $this->activeLinkFor($this->makeUser('pricing-author-'.Str::uuid().'@example.com'));
        $approver = $this->activeLinkFor($this->makeUser('pricing-approver-'.Str::uuid().'@example.com'));
        $manager = app(ConfigurationValueManager::class);

        $version = $manager->propose($definition, $value, 'Test.', $author);
        $version = $manager->submitForReview($version);
        $version = $manager->approve($version, $approver);
        $manager->activate($version, $approver, (string) Str::uuid());

        return $stableKey;
    }

    private function approvedVersionWithPricing(Campaign $campaign, int $priceValue = 500): CampaignVersion
    {
        $pricingKey = $this->activatedPricingDefinition($priceValue);

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            pricingConfigurationKey: $pricingKey,
            pricingConfigurationVersion: 1,
        );

        return app(CampaignVersionService::class)->approve($version, $this->makeRepresentative());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'format' => 'banner',
            'evidence' => ['completed' => true],
            'idempotency_key' => 'self-submit-'.Str::uuid(),
        ];
    }

    public function test_an_unauthenticated_subject_receives_401_and_creates_no_event(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->approvedVersionWithPricing($campaign);

        $response = $this->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $this->payload());

        $response->assertStatus(401);
        $this->assertSame(0, QualifiedEvent::query()->count());
    }

    public function test_an_authenticated_subject_without_a_grant_receives_a_safe_403(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->approvedVersionWithPricing($campaign);

        $user = $this->makeUser('no-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $this->payload());

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);
        $this->assertSame('no_active_grant', $response->json('reason'));
        $this->assertSame(0, QualifiedEvent::query()->count());
    }

    public function test_a_registered_beneficiary_self_submits_its_own_proof_with_a_server_resolved_price(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->approvedVersionWithPricing($campaign, priceValue: 777);

        $user = $this->makeUser('beneficiary-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $this->payload());

        $response->assertStatus(201);
        $response->assertJsonStructure(['qualified_event_id', 'billing_status']);

        $event = QualifiedEvent::query()->findOrFail($response->json('qualified_event_id'));
        $this->assertSame($link->id, $event->beneficiary_person_account_link_id);
        $this->assertSame(777, $event->applied_price_amount);
        $this->assertSame('pending', $event->billing_status->value);
    }

    /**
     * Aucun champ `applied_price_amount` ni `beneficiary_person_account_link_id`
     * n'existe même dans le formulaire validé
     * ({@see StoreSelfQualifiedEventRequest}) :
     * une tentative de les transmettre est simplement ignorée, jamais
     * acceptée comme une prétention.
     */
    public function test_client_supplied_price_and_beneficiary_claims_are_ignored(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->approvedVersionWithPricing($campaign, priceValue: 42);

        $user = $this->makeUser('honest-beneficiary-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $otherPerson = $this->makeRepresentative();

        $response = $this->actingAs($user)->postJson(
            "/advertising/campaign-versions/{$version->id}/qualified-events/self-submit",
            [...$this->payload(), 'applied_price_amount' => 999_999, 'beneficiary_person_account_link_id' => $otherPerson->id],
        );

        $response->assertStatus(201);

        $event = QualifiedEvent::query()->findOrFail($response->json('qualified_event_id'));
        $this->assertSame($link->id, $event->beneficiary_person_account_link_id);
        $this->assertSame(42, $event->applied_price_amount);
    }

    public function test_an_unapproved_campaign_version_is_denied_with_409(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $pricingKey = $this->activatedPricingDefinition(100);

        $draftVersion = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            pricingConfigurationKey: $pricingKey,
            pricingConfigurationVersion: 1,
        );

        $user = $this->makeUser('draft-attempt-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$draftVersion->id}/qualified-events/self-submit", $this->payload());

        $response->assertStatus(409);
        $this->assertSame('campaign_version_not_approved', $response->json('reason'));
    }

    public function test_an_unresolvable_pricing_configuration_is_denied_with_409(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            // Aucune configuration de prix épinglée.
        );
        $version = app(CampaignVersionService::class)->approve($version, $this->makeRepresentative());

        $user = $this->makeUser('no-pricing-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $this->payload());

        $response->assertStatus(409);
        $this->assertSame('pricing_not_resolvable', $response->json('reason'));
    }

    public function test_resubmitting_the_same_idempotency_key_returns_the_same_event(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->approvedVersionWithPricing($campaign);

        $user = $this->makeUser('idempotent-'.Str::uuid().'@example.com');
        $payload = $this->payload();

        $first = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $payload);
        $second = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $payload);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('qualified_event_id'), $second->json('qualified_event_id'));
        $this->assertSame(1, QualifiedEvent::query()->count());
    }

    public function test_insufficient_budget_is_denied_with_409(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10);
        $version = $this->approvedVersionWithPricing($campaign, priceValue: 10_000);

        $user = $this->makeUser('poor-campaign-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson("/advertising/campaign-versions/{$version->id}/qualified-events/self-submit", $this->payload());

        $response->assertStatus(409);
        $this->assertSame('insufficient_budget', $response->json('reason'));
    }

    public function test_the_self_submission_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('qualified-events/self-submit', $webRoutes);
        $this->assertTrue(Route::has('advertising.qualified-events.self-submit'));
    }
}
