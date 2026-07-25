<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Première porte d'entrée réelle du parcours annonceur (P007-W1) :
 * déclaration par une personne authentifiée de son propre dossier
 * annonceur (`advertiser_profile.create`, migration `2026_07_25_100012`).
 * Depuis P007, une inscription réelle émet automatiquement ce grant
 * (`user.base`, TD-0005-D) — voir `test_an_authenticated_subject_without_a_grant_receives_a_safe_403`
 * pour le seul moyen restant de constituer un sujet réellement sans grant.
 */
class AdvertiserProfileRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'legal_name' => 'Annonceur de test SARL',
            'legal_identifier' => 'RCCM-'.Str::random(8),
            'country_code' => 'CI',
            'territories' => ['CI'],
        ];
    }

    public function test_an_unauthenticated_subject_receives_401_and_creates_no_profile(): void
    {
        $response = $this->postJson('/advertising/advertiser-profile', $this->validPayload());

        $response->assertStatus(401);
        $this->assertSame(0, AdvertiserProfile::query()->count());
    }

    public function test_an_authenticated_subject_without_a_grant_receives_a_safe_403(): void
    {
        $user = $this->makeUser('no-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);

        $response = $this->actingAs($user)->postJson('/advertising/advertiser-profile', $this->validPayload());

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);
        $this->assertSame('no_active_grant', $response->json('reason'));

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }

        $this->assertSame(0, AdvertiserProfile::query()->count());
    }

    public function test_a_registered_subject_creates_its_own_advertiser_profile_as_representative(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);

        $response = $this->actingAs($user)->postJson('/advertising/advertiser-profile', $this->validPayload());

        $response->assertStatus(201);
        $response->assertJsonStructure(['advertiser_profile_id']);

        $profile = AdvertiserProfile::query()->findOrFail($response->json('advertiser_profile_id'));
        $this->assertSame($link->id, $profile->representative_person_account_link_id);
        $this->assertSame('active', $profile->status->value);
        $this->assertSame('CI', $profile->country_code);
    }

    /**
     * Boucle complète du parcours annonceur (P007-W1) : sans l'octroi
     * automatique du rôle modèle `advertiser.representative` déclenché par
     * `AdvertiserOnboardingService` (migration `2026_07_25_200002`), le
     * dossier créé ci-dessus resterait inutilisable — `campaign.create`
     * n'aurait jamais de grant actif. Ce test le prouve en enchaînant
     * réellement les deux routes HTTP.
     */
    public function test_becoming_an_advertiser_immediately_unlocks_campaign_creation(): void
    {
        $user = $this->makeUser('newly-advertiser-'.Str::uuid().'@example.com');

        $profileResponse = $this->actingAs($user)->postJson('/advertising/advertiser-profile', $this->validPayload());
        $profileResponse->assertStatus(201);

        $this->makeActiveSizeThreshold();
        $sector = $this->makeSectorClassification();

        $campaignResponse = $this->actingAs($user)->postJson('/advertising/campaigns', [
            'advertiser_profile_id' => $profileResponse->json('advertiser_profile_id'),
            'code' => 'campaign-'.Str::uuid(),
            'currency' => 'XOF',
            'sector_classification_id' => $sector->id,
            'creations' => ['headline' => 'Titre de test'],
            'expected_event' => ['format' => 'banner', 'condition' => 'completion'],
            'destination' => ['url' => 'https://annonceur.example.com'],
            'territory' => ['CI'],
            'pricing_configuration_key' => 'standard_cpm',
            'pricing_configuration_version' => 1,
            'audience' => ['criteria' => ['country' => 'CI'], 'estimated_size' => 10000],
        ]);

        $campaignResponse->assertStatus(201);
    }

    public function test_the_advertiser_profile_submission_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('advertising/advertiser-profile', $webRoutes);
        $this->assertTrue(Route::has('advertising.advertiser-profile.store'));
    }
}
