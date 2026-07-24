<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Services\CampaignService;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Première route sensible réelle de l'écosystème (P005-B) : soumission
 * d'une campagne en draft. Contrairement à `HttpAndWorkerParityTest` (P003-B2)
 * qui démontrait l'ABSENCE de toute route métier, cette suite démontre la
 * PRÉSENCE et le fonctionnement réel de la première — changement de
 * posture attendu et documenté dans le dossier P005-B.
 */
class CampaignSubmissionRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function realCapability(): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', 'campaign.create')
            ->where('state', 'active')
            ->firstOrFail();
    }

    private function grantCampaignCreate(PersonAccountLink $subject): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_campaign_create',
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
            capability: $this->realCapability(),
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
     * @return array<string, mixed>
     */
    private function validPayload(AdvertiserProfile $advertiser): array
    {
        $this->makeActiveSizeThreshold();
        $sector = $this->makeSectorClassification();

        return [
            'advertiser_profile_id' => $advertiser->id,
            'code' => 'campaign-'.Str::uuid(),
            'currency' => 'XOF',
            'sector_classification_id' => $sector->id,
            'creations' => ['headline' => 'Titre de test'],
            'expected_event' => ['format' => 'banner', 'condition' => 'completion'],
            'destination' => ['url' => 'https://annonceur.example.com'],
            'territory' => ['CI'],
            'pricing_configuration_key' => 'standard_cpm',
            'pricing_configuration_version' => 1,
            'audience' => [
                'criteria' => ['country' => 'CI'],
                'estimated_size' => 10000,
            ],
        ];
    }

    public function test_an_unauthenticated_subject_receives_401_and_creates_no_campaign(): void
    {
        $advertiser = $this->makeAdvertiserProfile();

        $response = $this->postJson('/advertising/campaigns', $this->validPayload($advertiser));

        $response->assertStatus(401);
        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_an_authenticated_subject_without_a_grant_receives_a_safe_403(): void
    {
        $user = $this->makeUser('no-grant-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);

        $response = $this->actingAs($user)->postJson('/advertising/campaigns', $this->validPayload($advertiser));

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);
        // `no_active_grant` est le code de motif sûr attendu ici : c'est le
        // nom de la catégorie de refus (vocabulaire documenté d'AuthorizationReason),
        // jamais un identifiant de grant, une politique ou une définition de
        // capacité — voir les fragments réellement interdits ci-dessous
        // (identiques à P003-B2/B4).
        $this->assertSame('no_active_grant', $response->json('reason'));

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }

        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_a_subject_with_an_active_grant_creates_the_campaign_and_draft_version_with_itself_as_author(): void
    {
        $user = $this->makeUser('with-grant-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $this->grantCampaignCreate($link);

        $response = $this->actingAs($user)->postJson('/advertising/campaigns', $this->validPayload($advertiser));

        $response->assertStatus(201);
        $response->assertJsonStructure(['campaign_id', 'campaign_version_id', 'state']);
        $this->assertSame('draft', $response->json('state'));

        $campaign = Campaign::query()->findOrFail($response->json('campaign_id'));
        $this->assertSame($advertiser->id, $campaign->advertiser_profile_id);

        $version = $campaign->versions()->sole();
        $this->assertSame($link->id, $version->author_person_account_link_id);
        $this->assertSame('draft', $version->state->value);
    }

    /**
     * Même invariant que P003-B2 test 13 : un identifiant transmis par le
     * client (ici `advertiser_profile_id`) ne remplace jamais l'appartenance
     * réellement persistée. Le sujet A détient un grant `campaign.create`
     * parfaitement actif — mais scopé à lui-même — et tente de créer une
     * campagne pour le dossier annonceur du sujet B : la portée du grant,
     * comparée au représentant réellement persisté de B (jamais à la
     * prétention du corps de requête), doit refuser.
     */
    public function test_a_client_supplied_advertiser_profile_id_never_overrides_the_persisted_representative(): void
    {
        $subjectA = $this->makeUser('spoofer-'.Str::uuid().'@example.com');
        $linkA = $this->activeLinkFor($subjectA);
        $this->grantCampaignCreate($linkA);

        $representativeB = $this->makeRepresentative();
        $advertiserOfB = $this->makeAdvertiserProfile($representativeB);

        $response = $this->actingAs($subjectA)->postJson('/advertising/campaigns', $this->validPayload($advertiserOfB));

        $response->assertStatus(403);
        $this->assertSame(0, Campaign::query()->count());
    }

    /**
     * Contrairement à `HttpAndWorkerParityTest::test_no_business_route_was_added_to_the_application`
     * (P003-B2), qui démontrait l'absence de toute route métier avant ce
     * lot, ce test confirme la présence de la première route réelle
     * (changement de posture volontaire, documenté au dossier P005-B).
     */
    public function test_the_campaign_submission_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('advertising/campaigns', $webRoutes);
        $this->assertTrue(Route::has('advertising.campaigns.store'));
    }

    public function test_the_campaign_create_capability_is_immutable_once_active(): void
    {
        $capability = $this->realCapability();

        $this->expectException(QueryException::class);

        DB::table('governance.capability_definitions')
            ->where('id', $capability->id)
            ->update(['risk_class' => 'critical']);
    }

    /**
     * Aucune écriture d'« écriture » comptable (`ledger.ledger_transactions`,
     * `ledger.postings`) n'est produite par cette route (ADR-0010 §4 : aucun
     * budget engagé au stade draft). `ledger.accounts` gagne délibérément
     * trois lignes : {@see CampaignService::createCampaign()}
     * (P005-A, inchangé par ce lot) y provisionne le plan de comptes dédié
     * de la campagne (disponible/réservé/consommé) — une ouverture de
     * compte, jamais une écriture comptable équilibrée. Interprétation
     * documentée au dossier P005-B : « aucune écriture ledger.* » désigne
     * `ledger.ledger_transactions`/`ledger.postings`, pas la table de plan
     * de comptes elle-même.
     */
    public function test_no_ledger_postings_or_transactions_are_produced_by_this_route(): void
    {
        $user = $this->makeUser('ledger-check-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $this->grantCampaignCreate($link);

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();
        $postingsBefore = DB::table('ledger.postings')->count();

        $response = $this->actingAs($user)->postJson('/advertising/campaigns', $this->validPayload($advertiser));

        $response->assertStatus(201);

        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame($postingsBefore, DB::table('ledger.postings')->count());
    }
}
