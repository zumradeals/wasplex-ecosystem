<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Upload d'une image publicitaire avant création de campagne (Lot 6, véto
 * du dirigeant 2026-07-30) — même mirroir d'autorisation et de grant de
 * test que `CampaignVideoUploadRouteTest` (`campaign.create`, jamais
 * auto-octroyé à un représentant constitué directement en base par
 * `makeAdvertiserProfile()`). L'orientation réelle est toujours mesurée
 * par `getimagesize()` après stockage (GD, disponible dans cet
 * environnement) — jamais une déclaration du client.
 */
class CampaignImageUploadRouteTest extends AdvertisingTestCase
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

    /**
     * Mirroir multipart de `postJson` : `postFormData` (resources/js/lib/api.ts)
     * envoie toujours `Accept: application/json` — la réponse d'erreur
     * doit donc toujours être structurée (voir `CampaignVideoUploadRouteTest`
     * pour l'incident qui a établi ce mirroir).
     */
    private function postImage(array $data): TestResponse
    {
        return $this->post('/advertising/campaign-images', $data, ['Accept' => 'application/json']);
    }

    public function test_a_guest_receives_a_structured_401(): void
    {
        $advertiserProfile = $this->makeAdvertiserProfile();

        $response = $this->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            'image' => UploadedFile::fake()->image('photo.jpg', 800, 1200),
        ]);

        $response->assertStatus(401);
    }

    public function test_a_stranger_to_the_advertiser_profile_receives_a_safe_403(): void
    {
        $advertiserProfile = $this->makeAdvertiserProfile();
        $stranger = $this->makeRepresentative();
        $this->grantCampaignCreate($stranger);

        $response = $this->actingAs($stranger->user)->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            'image' => UploadedFile::fake()->image('photo.jpg', 800, 1200),
        ]);

        $response->assertStatus(403);
    }

    public function test_a_portrait_image_is_accepted(): void
    {
        Storage::fake('public');
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            'image' => UploadedFile::fake()->image('photo.jpg', 800, 1200),
        ]);

        $response->assertCreated();
        $response->assertJson(['width' => 800, 'height' => 1200]);
        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_a_square_image_is_accepted(): void
    {
        Storage::fake('public');
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            'image' => UploadedFile::fake()->image('photo.jpg', 1000, 1000),
        ]);

        $response->assertCreated();
    }

    public function test_a_landscape_image_is_rejected_and_the_file_is_deleted(): void
    {
        Storage::fake('public');
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            'image' => UploadedFile::fake()->image('photo.jpg', 1200, 800),
        ]);

        $response->assertStatus(422);
        $this->assertSame('image_orientation_refused', $response->json('reason'));
        Storage::disk('public')->assertDirectoryEmpty('campaign-images');
    }

    public function test_an_undecodable_image_is_rejected(): void
    {
        Storage::fake('public');
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            // Extension/mimetype déclarés « image », contenu réel non
            // décodable — même discipline que ffprobe pour la vidéo.
            'image' => UploadedFile::fake()->create('fake.jpg', 10),
        ]);

        $response->assertStatus(422);
        $this->assertSame('image_unreadable', $response->json('reason'));
    }

    public function test_a_non_image_file_is_rejected_by_validation(): void
    {
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postImage([
            'advertiser_profile_id' => $advertiserProfile->id,
            'image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ]);

        $response->assertStatus(422);
    }

    public function test_the_campaign_image_upload_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.campaign-images.store'));
    }
}
