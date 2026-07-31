<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\FrequencyCapBounds;
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
            // Part utilisateur du ratio 50/50 exact (AMD-0002), pas le
            // prix de base entier réservé sur le budget campagne :
            // intdiv(777 + 1, 2).
            ->where('ads.0.reward_amount', 389)
            ->where('ads.0.currency', $campaign->currency)
            ->where('ads.0.format', 'banner')
            ->where('ads.0.condition', 'completion'),
        );
    }

    public function test_shares_a_zero_wallet_balance_for_a_person_without_any_credit(): void
    {
        $user = $this->makeUser('feed-wallet-zero-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        // Un vrai zéro, pas une devise inventée (CLAUDE.md §6) : la devise
        // n'existera qu'au premier crédit réel.
        $response->assertInertia(fn (Assert $page) => $page
            ->where('wallet_balance.available', 0)
            ->where('wallet_balance.currency', null),
        );
    }

    public function test_shares_the_real_balance_after_an_accepted_distribution(): void
    {
        $campaign = $this->eligibleCampaign(funding: 10_000, reward: 1_000);
        $version = $campaign->versions()->firstOrFail();

        $user = $this->makeUser('feed-wallet-credited-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $link,
            format: 'banner',
            evidence: ['completed' => true],
            appliedPriceAmount: 1_000,
            idempotencyKey: 'feed-balance-'.Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
        $this->budgetService()->acceptQualifiedEvent($event);

        $response = $this->actingAs($user)->get('/dashboard');

        // Part utilisateur du ratio 50/50 exact (AMD-0002).
        $response->assertInertia(fn (Assert $page) => $page
            ->where('wallet_balance.available', 500)
            ->where('wallet_balance.currency', $campaign->currency),
        );
    }

    public function test_excludes_a_campaign_once_the_person_has_reached_their_frequency_cap(): void
    {
        // Borne resserrée à 1/jour, 1 au total (instruction explicite du
        // fondateur, 2026-07-31) pour que la seule vue déjà acceptée
        // suffise à épuiser le plafond.
        FrequencyCapBounds::query()->update(['state' => 'retired']);
        FrequencyCapBounds::create([
            'daily_free_view_limit' => 1,
            'lifetime_free_view_limit' => 1,
            'version' => 99,
            'state' => 'active',
        ]);

        $campaign = $this->eligibleCampaign(funding: 10_000, reward: 1_000);
        $version = $campaign->versions()->firstOrFail();

        $user = $this->makeUser('feed-frequency-cap-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $link,
            format: 'banner',
            evidence: ['completed' => true],
            appliedPriceAmount: 1_000,
            idempotencyKey: 'feed-frequency-cap-'.Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
        $this->budgetService()->acceptQualifiedEvent($event);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('ads', 0),
        );
    }

    public function test_shares_no_wallet_balance_without_the_wallet_view_capability(): void
    {
        $user = $this->makeUser('feed-wallet-ungoverned-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        // Même gouvernance que GET /wallet/balance : sans `wallet.view`
        // actif, aucun solde partagé — le Feed reste utilisable, le
        // compteur est simplement absent, jamais un zéro trompeur.
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('wallet_balance', null),
        );
    }

    public function test_shows_zero_social_counts_and_false_viewer_state_by_default(): void
    {
        $this->eligibleCampaign();

        $user = $this->makeUser('feed-social-zero-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('ads.0.likes_count', 0)
            ->where('ads.0.favorites_count', 0)
            ->where('ads.0.shares_count', 0)
            ->where('ads.0.liked', false)
            ->where('ads.0.favorited', false),
        );
    }

    public function test_shows_real_social_counts_and_the_viewers_own_state(): void
    {
        $campaign = $this->eligibleCampaign();
        $version = $campaign->versions()->firstOrFail();

        $viewer = $this->makeUser('feed-social-viewer-'.Str::uuid().'@example.com');
        $viewer->forceFill(['email_verified_at' => now()])->save();
        $otherLiker = $this->activeLinkFor($this->makeUser('feed-social-other-'.Str::uuid().'@example.com'));

        $this->actingAs($viewer)->postJson("/advertising/campaign-versions/{$version->id}/like")->assertStatus(200);
        $this->actingAs($viewer)->postJson("/advertising/campaign-versions/{$version->id}/favorite")->assertStatus(200);
        $this->actingAs($viewer)->postJson("/advertising/campaign-versions/{$version->id}/share")->assertStatus(201);
        // Un deuxième compte aime aussi : le compteur agrège tout le monde,
        // l'état viewer ne reflète que le sujet courant.
        $this->actingAs($otherLiker->user)->postJson("/advertising/campaign-versions/{$version->id}/like")->assertStatus(200);

        $response = $this->actingAs($viewer)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('ads.0.likes_count', 2)
            ->where('ads.0.favorites_count', 1)
            ->where('ads.0.shares_count', 1)
            ->where('ads.0.liked', true)
            ->where('ads.0.favorited', true),
        );
    }

    public function test_the_feed_route_is_registered(): void
    {
        $this->assertTrue(Route::has('dashboard'));
    }
}
