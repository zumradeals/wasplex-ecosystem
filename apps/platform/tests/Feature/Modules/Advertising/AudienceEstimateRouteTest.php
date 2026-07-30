<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Aperçu en direct de la taille d'audience avant création de campagne
 * (Lot 3, véto du dirigeant) — même mirroir d'autorisation que
 * `CampaignController::store()` (`campaign.create`). Mirroir du grant de
 * test de `CampaignSubmissionRouteTest` : `AdvertisingTestCase::makeAdvertiserProfile()`
 * crée le dossier directement en base (jamais via le vrai flux
 * d'onboarding), donc `campaign.create` n'est jamais auto-octroyé pour le
 * représentant de test — un grant explicite reste nécessaire ici.
 */
class AudienceEstimateRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function grantCampaignCreate(PersonAccountLink $subject): Grant
    {
        $capability = CapabilityDefinition::query()
            ->where('stable_key', 'campaign.create')
            ->where('state', 'active')
            ->firstOrFail();

        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_campaign_create_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'campaign.create'.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: $correlationId,
        );

        return $manager->activate($grant, $author, null, $correlationId);
    }

    public function test_a_guest_receives_a_structured_401(): void
    {
        $advertiserProfile = $this->makeAdvertiserProfile();

        $response = $this->postJson('/advertising/audience-estimate', [
            'advertiser_profile_id' => $advertiserProfile->id,
            'criteria' => ['country' => ['CI']],
        ]);

        $response->assertStatus(401);
    }

    public function test_a_stranger_to_the_advertiser_profile_receives_a_safe_403(): void
    {
        $advertiserProfile = $this->makeAdvertiserProfile();
        $stranger = $this->makeRepresentative();
        $this->grantCampaignCreate($stranger);

        $response = $this->actingAs($stranger->user)->postJson('/advertising/audience-estimate', [
            'advertiser_profile_id' => $advertiserProfile->id,
            'criteria' => ['country' => ['CI']],
        ]);

        $response->assertStatus(403);
    }

    public function test_the_representative_receives_the_computed_estimate(): void
    {
        $this->makeActiveSizeThreshold(1);
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        PersonAdvertisingProfile::create([
            'person_id' => Person::create()->id,
            'country_code' => 'CI',
            'interests' => [],
            'consent_version' => 1,
            'consent_given_at' => now(),
        ]);

        $response = $this->actingAs($representative->user)->postJson('/advertising/audience-estimate', [
            'advertiser_profile_id' => $advertiserProfile->id,
            'criteria' => ['country' => ['CI']],
        ]);

        $response->assertOk();
        $response->assertJson(['estimated_size' => 1, 'below_threshold' => false]);
    }

    public function test_a_forbidden_sensitive_criterion_is_refused(): void
    {
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postJson('/advertising/audience-estimate', [
            'advertiser_profile_id' => $advertiserProfile->id,
            'criteria' => ['religion' => 'any'],
        ]);

        $response->assertStatus(422);
        $this->assertSame('forbidden_targeting_criterion', $response->json('reason'));
    }

    public function test_the_audience_estimate_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.audience-estimate.store'));
    }
}
