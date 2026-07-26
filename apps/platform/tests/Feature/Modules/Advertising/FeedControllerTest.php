<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Feed (W4, L03) : point d'entrée quotidien de l'utilisateur — même
 * protocole que `WalletOverviewPageTest`/`AdvertisingOverviewPageTest`
 * (groupe `auth`, redirection vers la connexion pour un invité). Aucune
 * capacité nouvelle ici : parcourir les campagnes diffusées est un
 * contenu public, jamais une ressource personnelle.
 */
class FeedControllerTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private int $pricingCounter = 0;

    private function activatedPricingDefinition(int $value): string
    {
        $stableKey = 'test.feed_pricing_'.(++$this->pricingCounter);

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

        $author = $this->activeLinkFor($this->makeUser('feed-pricing-author-'.Str::uuid().'@example.com'));
        $approver = $this->activeLinkFor($this->makeUser('feed-pricing-approver-'.Str::uuid().'@example.com'));
        $manager = app(ConfigurationValueManager::class);

        $version = $manager->propose($definition, $value, 'Test.', $author);
        $version = $manager->submitForReview($version);
        $version = $manager->approve($version, $approver);
        $manager->activate($version, $approver, (string) Str::uuid());

        return $stableKey;
    }

    private function eligibleCampaign(int $funding = 10_000, int $reward = 500, string $headline = 'Publicité de test'): Campaign
    {
        $pricingKey = $this->activatedPricingDefinition($reward);
        $campaign = $this->makeCampaign();

        if ($funding > 0) {
            $this->fundCampaign($campaign, $funding);
        }

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => $headline],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            pricingConfigurationKey: $pricingKey,
            pricingConfigurationVersion: 1,
        );

        app(CampaignVersionService::class)->approve($version, $this->makeRepresentative());

        return $campaign;
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_shows_no_ads_when_no_campaign_is_eligible(): void
    {
        $user = $this->makeUser('feed-empty-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('ads', []),
        );
    }

    public function test_excludes_a_campaign_with_no_available_budget(): void
    {
        $this->eligibleCampaign(funding: 0, reward: 500);

        $user = $this->makeUser('feed-no-budget-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page->where('ads', []));
    }

    public function test_excludes_a_campaign_whose_reward_exceeds_available_budget(): void
    {
        $this->eligibleCampaign(funding: 100, reward: 500);

        $user = $this->makeUser('feed-underfunded-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page->where('ads', []));
    }

    public function test_excludes_a_campaign_with_no_approved_version(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        // Une seule version, jamais approuvée (reste draft).
        app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Jamais approuvée'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
        );

        $user = $this->makeUser('feed-draft-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page->where('ads', []));
    }

    public function test_excludes_an_approved_campaign_whose_pricing_is_not_resolvable(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);

        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $this->makeSectorClassification(),
            creations: ['headline' => 'Sans prix'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
            // Aucune configuration de prix épinglée.
        );
        app(CampaignVersionService::class)->approve($version, $this->makeRepresentative());

        $user = $this->makeUser('feed-no-pricing-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page->where('ads', []));
    }

    public function test_shows_an_eligible_campaign_with_its_resolved_reward(): void
    {
        $campaign = $this->eligibleCampaign(funding: 10_000, reward: 777, headline: 'Découvrez notre offre');

        $user = $this->makeUser('feed-eligible-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('ads', 1)
            ->where('ads.0.advertiser', $campaign->advertiserProfile->legal_name)
            ->where('ads.0.headline', 'Découvrez notre offre')
            ->where('ads.0.reward_amount', 777)
            ->where('ads.0.currency', $campaign->currency)
            ->where('ads.0.format', 'banner')
            ->where('ads.0.condition', 'completion'),
        );
    }

    public function test_the_feed_route_is_registered(): void
    {
        $this->assertTrue(Route::has('dashboard'));
    }
}
