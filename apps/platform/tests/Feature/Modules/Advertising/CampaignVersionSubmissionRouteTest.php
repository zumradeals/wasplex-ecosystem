<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignVersionService;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Deuxième route sensible réelle de l'écosystème (P005-C) : soumission
 * d'une CampaignVersion `draft` vers `in_review` (ADR-0010 §3, §5).
 */
class CampaignVersionSubmissionRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function capability(string $stableKey): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', $stableKey)
            ->where('state', 'active')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function grantSubmitForReview(PersonAccountLink $subject, array $scope = ['self' => true]): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_submit_for_review_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'campaign.submit_for_review'.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $this->capability('campaign.submit_for_review'),
            policy: $policy,
            scope: ScopePayload::fromArray($scope),
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

    private function draftVersion(?AdvertiserProfile $advertiser = null, ?PersonAccountLink $author = null): CampaignVersion
    {
        $advertiser ??= $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $sector = $this->makeSectorClassification();

        return app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $sector,
            creations: ['headline' => 'Titre de test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $author ?? $advertiser->representative,
        );
    }

    public function test_an_unauthenticated_subject_receives_401_and_leaves_the_version_untouched(): void
    {
        $version = $this->draftVersion();

        $response = $this->postJson("/advertising/campaign-versions/{$version->id}/submit-for-review");

        $response->assertStatus(401);
        $this->assertSame(CampaignVersionState::Draft, $version->fresh()->state);
    }

    public function test_the_author_submits_their_own_draft_version_for_review_successfully(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $version = $this->draftVersion($advertiser, $representative);

        // Le grant est porté par le représentant lui-même : l'utilisateur
        // HTTP authentifié doit être celui associé à cette liaison.
        $this->grantSubmitForReview($representative);

        $response = $this->actingAs($representative->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/submit-for-review"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['campaign_version_id', 'state']);
        $this->assertSame('in_review', $response->json('state'));
        $this->assertSame(CampaignVersionState::InReview, $version->fresh()->state);
    }

    /**
     * Même invariant anti-usurpation que P005-B (test #4 de
     * CampaignSubmissionRouteTest) : un grant scopé à soi-même ne couvre
     * jamais le dossier annonceur d'un tiers.
     */
    public function test_a_subject_without_a_grant_covering_the_advertiser_profile_cannot_submit_another_dossiers_version(): void
    {
        $subjectA = $this->makeRepresentative();
        $this->grantSubmitForReview($subjectA);

        $advertiserOfB = $this->makeAdvertiserProfile();
        $versionOfB = $this->draftVersion($advertiserOfB);

        $response = $this->actingAs($subjectA->user)->postJson(
            "/advertising/campaign-versions/{$versionOfB->id}/submit-for-review"
        );

        $response->assertStatus(403);
        $this->assertSame(CampaignVersionState::Draft, $versionOfB->fresh()->state);
    }

    public function test_the_campaign_version_submission_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('campaign-versions/{campaignVersion}/submit-for-review', $webRoutes);
        $this->assertTrue(Route::has('advertising.campaign-versions.submit-for-review'));
    }

    public function test_no_ledger_postings_or_transactions_are_produced_by_this_route(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $version = $this->draftVersion($advertiser, $representative);
        $this->grantSubmitForReview($representative);

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();
        $postingsBefore = DB::table('ledger.postings')->count();

        $response = $this->actingAs($representative->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/submit-for-review"
        );

        $response->assertStatus(200);
        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame($postingsBefore, DB::table('ledger.postings')->count());
    }
}
