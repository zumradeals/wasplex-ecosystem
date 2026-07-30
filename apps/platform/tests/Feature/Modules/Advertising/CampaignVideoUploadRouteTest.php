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
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Upload d'une vidéo publicitaire avant création de campagne (Lot 4, véto du
 * dirigeant 2026-07-30) — même mirroir d'autorisation et de grant de test
 * qu'`AudienceEstimateRouteTest` (`campaign.create`, jamais auto-octroyé à un
 * représentant constitué directement en base par `makeAdvertiserProfile()`).
 * La durée est toujours simulée via `Process::fake` (ffprobe réel non
 * nécessaire en test) — jamais une valeur déclarée par la requête
 * elle-même. Le préfixe `*` (pas seulement suffixe) est nécessaire :
 * Symfony `Process::getCommandLine()` échappe chaque argument entre
 * apostrophes, donc la ligne réelle commence par `'ffprobe'`, pas `ffprobe`.
 */
class CampaignVideoUploadRouteTest extends AdvertisingTestCase
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
     * envoie toujours `Accept: application/json`, jamais un formulaire HTML
     * classique — la réponse d'erreur doit donc toujours être structurée.
     */
    private function postVideo(array $data): TestResponse
    {
        return $this->post('/advertising/campaign-videos', $data, ['Accept' => 'application/json']);
    }

    public function test_a_guest_receives_a_structured_401(): void
    {
        $advertiserProfile = $this->makeAdvertiserProfile();

        $response = $this->postVideo([
            'advertiser_profile_id' => $advertiserProfile->id,
            'video' => UploadedFile::fake()->create('pub.mp4', 500, 'video/mp4'),
        ]);

        $response->assertStatus(401);
    }

    public function test_a_stranger_to_the_advertiser_profile_receives_a_safe_403(): void
    {
        $advertiserProfile = $this->makeAdvertiserProfile();
        $stranger = $this->makeRepresentative();
        $this->grantCampaignCreate($stranger);

        $response = $this->actingAs($stranger->user)->postVideo([
            'advertiser_profile_id' => $advertiserProfile->id,
            'video' => UploadedFile::fake()->create('pub.mp4', 500, 'video/mp4'),
        ]);

        $response->assertStatus(403);
    }

    public function test_a_video_within_the_active_bounds_is_accepted(): void
    {
        Storage::fake('public');
        Process::fake(['*ffprobe*' => Process::result(output: '45')]);
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postVideo([
            'advertiser_profile_id' => $advertiserProfile->id,
            'video' => UploadedFile::fake()->create('pub.mp4', 500, 'video/mp4'),
        ]);

        $response->assertCreated();
        $response->assertJson(['duration_seconds' => 45]);
        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_a_video_outside_the_active_bounds_is_rejected_and_the_file_is_deleted(): void
    {
        Storage::fake('public');
        Process::fake(['*ffprobe*' => Process::result(output: '5')]);
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postVideo([
            'advertiser_profile_id' => $advertiserProfile->id,
            'video' => UploadedFile::fake()->create('pub.mp4', 500, 'video/mp4'),
        ]);

        $response->assertStatus(422);
        $this->assertSame('video_duration_out_of_bounds', $response->json('reason'));
        Storage::disk('public')->assertDirectoryEmpty('campaign-videos');
    }

    public function test_an_unreadable_duration_is_rejected(): void
    {
        Storage::fake('public');
        Process::fake(['*ffprobe*' => Process::result(output: '', exitCode: 1)]);
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postVideo([
            'advertiser_profile_id' => $advertiserProfile->id,
            'video' => UploadedFile::fake()->create('pub.mp4', 500, 'video/mp4'),
        ]);

        $response->assertStatus(422);
        $this->assertSame('video_duration_unreadable', $response->json('reason'));
    }

    public function test_a_non_mp4_file_is_rejected_by_validation(): void
    {
        $representative = $this->makeRepresentative();
        $this->grantCampaignCreate($representative);
        $advertiserProfile = $this->makeAdvertiserProfile($representative);

        $response = $this->actingAs($representative->user)->postVideo([
            'advertiser_profile_id' => $advertiserProfile->id,
            'video' => UploadedFile::fake()->create('pub.txt', 10, 'text/plain'),
        ]);

        $response->assertStatus(422);
    }

    public function test_the_campaign_video_upload_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.campaign-videos.store'));
    }
}
