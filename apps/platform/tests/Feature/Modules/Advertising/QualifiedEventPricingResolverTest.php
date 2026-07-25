<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Moteur de prix versionné pour l'événement qualifié (W2) : ferme la dette
 * documentée sur `event.submit` (migration `2026_07_25_100009`, TD-0004).
 * Résout toujours via le registre Configuration, échoue fermé sinon
 * (ADR-0002 §7.3) — jamais un montant deviné ou par défaut.
 */
class QualifiedEventPricingResolverTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function resolver(): QualifiedEventPricingResolver
    {
        return app(QualifiedEventPricingResolver::class);
    }

    private function activatePricing(string $stableKey, mixed $value, ValueType $valueType = ValueType::Integer): int
    {
        $definition = Definition::create([
            'stable_key' => $stableKey,
            'version' => 1,
            'domain' => 'advertising',
            'level' => ConfigurationLevel::C2,
            'value_type' => $valueType,
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

        return 1;
    }

    public function test_resolves_the_base_price_pinned_by_a_campaign_version(): void
    {
        $this->activatePricing('test.pricing_key_resolved', 250);

        $campaign = $this->makeCampaign();
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            pricingConfigurationKey: 'test.pricing_key_resolved',
            pricingConfigurationVersion: 1,
        );

        $this->assertSame(250, $this->resolver()->resolveBasePrice($version));
    }

    public function test_fails_closed_when_no_pricing_configuration_is_pinned(): void
    {
        $campaign = $this->makeCampaign();
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
        );

        $this->expectException(PricingConfigurationNotResolvableException::class);

        $this->resolver()->resolveBasePrice($version);
    }

    public function test_fails_closed_when_the_pinned_key_does_not_exist(): void
    {
        $campaign = $this->makeCampaign();
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            pricingConfigurationKey: 'test.never_declared',
            pricingConfigurationVersion: 1,
        );

        $this->expectException(PricingConfigurationNotResolvableException::class);

        $this->resolver()->resolveBasePrice($version);
    }

    public function test_fails_closed_when_the_definition_is_not_an_integer(): void
    {
        $this->activatePricing('test.pricing_key_not_integer', '250', ValueType::String);

        $campaign = $this->makeCampaign();
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            pricingConfigurationKey: 'test.pricing_key_not_integer',
            pricingConfigurationVersion: 1,
        );

        $this->expectException(PricingConfigurationNotResolvableException::class);

        $this->resolver()->resolveBasePrice($version);
    }
}
