<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Enums\ReviewLevel;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\SectorClassification;
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
 * Troisième route sensible réelle de l'écosystème (P005-C) : approbation
 * d'une CampaignVersion `in_review` (ADR-0010 §3, §5), avec séparation des
 * tâches obligatoire pour les secteurs à `review_level = enhanced`
 * (ADR-0004 §12).
 */
class CampaignVersionApprovalRouteTest extends AdvertisingTestCase
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
     * `campaign.approve` est déclarée `risk_class = sensitive` : l'activer
     * exige déjà, au niveau du grant lui-même, un approbateur distinct de
     * l'auteur de la proposition ET du sujet ({@see GrantManager::activate()},
     * P003-B3, TD-0001-A) — défense en profondeur au niveau de l'octroi du
     * pouvoir, avant même toute décision d'approbation d'une campagne.
     *
     * @param  array<string, mixed>  $scope
     */
    private function grantApprove(PersonAccountLink $subject, array $scope = ['self' => true]): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_campaign_approve_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'campaign.approve'.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));
        $grantApprover = $this->activeLinkFor($this->makeUser('grant-approver-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $this->capability('campaign.approve'),
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

        return $manager->activate($grant, $author, $grantApprover, $correlationId);
    }

    private function inReviewVersion(
        ?AdvertiserProfile $advertiser = null,
        ?PersonAccountLink $author = null,
        ?SectorClassification $sector = null,
    ): CampaignVersion {
        $advertiser ??= $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser);
        $author ??= $advertiser->representative;

        $service = app(CampaignVersionService::class);

        $version = $service->propose(
            campaign: $campaign,
            sector: $sector ?? $this->makeSectorClassification(),
            creations: ['headline' => 'Titre de test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $author,
        );

        return $service->submitForReview($version);
    }

    public function test_an_unauthenticated_subject_receives_401_and_leaves_the_version_untouched(): void
    {
        $version = $this->inReviewVersion();

        $response = $this->postJson("/advertising/campaign-versions/{$version->id}/approve");

        $response->assertStatus(401);
        $this->assertSame(CampaignVersionState::InReview, $version->fresh()->state);
    }

    /**
     * Secteur standard : toute personne disposant d'un grant `campaign.approve`
     * actif sur le dossier annonceur peut approuver — y compris l'auteur, si
     * le secteur est standard ? Non : {@see CampaignVersionService::approve()}
     * refuse inconditionnellement l'auto-approbation (contrainte SQL
     * `campaign_versions_author_not_approver_check`, défense en profondeur
     * sans exception de niveau de revue — voir
     * `CampaignVersionSeparationOfDutiesTest::test_self_approval_is_refused_even_at_standard_review_level`,
     * P005-A). Ce choix, déjà posé et testé par un lot antérieur fusionné,
     * n'est pas rouvert par P005-C : ce test utilise donc un approbateur
     * distinct de l'auteur même pour un secteur standard.
     */
    public function test_standard_review_level_grant_holder_approves_successfully(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Standard);
        $version = $this->inReviewVersion($advertiser, $representative, $sector);

        $approver = $this->makeRepresentative();
        $this->grantApprove($approver, ['resource_type' => 'advertising.advertiser_profile', 'resource_ids' => [$advertiser->id]]);

        $response = $this->actingAs($approver->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/approve"
        );

        $response->assertStatus(200);
        $this->assertSame('approved', $response->json('state'));
        $this->assertSame(CampaignVersionState::Approved, $version->fresh()->state);
        $this->assertSame($approver->id, $version->fresh()->approver_person_account_link_id);
    }

    /**
     * Secteur enhanced : l'auteur, même détenteur d'un grant `campaign.approve`
     * actif, est refusé s'il tente d'approuver sa propre version
     * (ADR-0010 §5).
     */
    public function test_enhanced_review_level_author_with_a_grant_is_refused_self_approval(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Enhanced);
        $version = $this->inReviewVersion($advertiser, $representative, $sector);

        $this->grantApprove($representative, ['resource_type' => 'advertising.advertiser_profile', 'resource_ids' => [$advertiser->id]]);

        $response = $this->actingAs($representative->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/approve"
        );

        $response->assertStatus(403);
        $this->assertSame('self_approval_refused', $response->json('reason'));
        $this->assertSame(CampaignVersionState::InReview, $version->fresh()->state);
    }

    /**
     * Secteur enhanced : une personne distincte de l'auteur, avec un grant
     * `campaign.approve` actif, approuve avec succès.
     */
    public function test_enhanced_review_level_distinct_grant_holder_approves_successfully(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Enhanced);
        $version = $this->inReviewVersion($advertiser, $representative, $sector);

        $approver = $this->makeRepresentative();
        $this->grantApprove($approver, ['resource_type' => 'advertising.advertiser_profile', 'resource_ids' => [$advertiser->id]]);

        $response = $this->actingAs($approver->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/approve"
        );

        $response->assertStatus(200);
        $this->assertSame(CampaignVersionState::Approved, $version->fresh()->state);
        $this->assertSame($approver->id, $version->fresh()->approver_person_account_link_id);
    }

    /**
     * Même invariant anti-usurpation que P005-B/P005-C soumission : un
     * grant scopé à soi-même ne couvre jamais le dossier annonceur d'un
     * tiers.
     */
    public function test_a_subject_without_a_grant_covering_the_advertiser_profile_cannot_approve_another_dossiers_version(): void
    {
        $subjectA = $this->makeRepresentative();
        $this->grantApprove($subjectA);

        $advertiserOfB = $this->makeAdvertiserProfile();
        $versionOfB = $this->inReviewVersion($advertiserOfB);

        $response = $this->actingAs($subjectA->user)->postJson(
            "/advertising/campaign-versions/{$versionOfB->id}/approve"
        );

        $response->assertStatus(403);
        $this->assertSame(CampaignVersionState::InReview, $versionOfB->fresh()->state);
    }

    public function test_the_campaign_version_approval_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('campaign-versions/{campaignVersion}/approve', $webRoutes);
        $this->assertTrue(Route::has('advertising.campaign-versions.approve'));
    }

    public function test_no_ledger_postings_or_transactions_are_produced_by_this_route(): void
    {
        $representative = $this->makeRepresentative();
        $advertiser = $this->makeAdvertiserProfile($representative);
        $sector = $this->makeSectorClassification(reviewLevel: ReviewLevel::Standard);
        $version = $this->inReviewVersion($advertiser, $representative, $sector);

        $approver = $this->makeRepresentative();
        $this->grantApprove($approver, ['resource_type' => 'advertising.advertiser_profile', 'resource_ids' => [$advertiser->id]]);

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();
        $postingsBefore = DB::table('ledger.postings')->count();

        $response = $this->actingAs($approver->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/approve"
        );

        $response->assertStatus(200);
        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame($postingsBefore, DB::table('ledger.postings')->count());
    }
}
