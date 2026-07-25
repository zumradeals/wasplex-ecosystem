<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\ModerationCase;
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
 * Quatrième route sensible réelle de l'écosystème (P005-D) : signalement
 * d'une campagne (`03-signalements-sanctions-et-remuneration.md` §1),
 * largement disponible à tout utilisateur authentifié — voir le
 * raisonnement de portée documenté sur `campaign.report`
 * (migration `2026_07_25_100006`).
 */
class CampaignReportRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function realCapability(): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', 'campaign.report')
            ->where('state', 'active')
            ->firstOrFail();
    }

    private function grantReport(PersonAccountLink $subject): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_campaign_report_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'campaign.report'.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $this->realCapability(),
            policy: $policy,
            // Portée délibérément non `self` : n'importe quelle campagne,
            // jamais seulement celles du propre dossier annonceur du
            // signaleur (raisonnement documenté sur la déclaration de la
            // capacité).
            scope: ScopePayload::fromArray(['resource_type' => 'advertising.campaign']),
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
    private function validPayload(): array
    {
        return [
            'reason' => 'Destination non conforme au format annoncé',
            'severity' => 'high',
            'observed_destination' => 'https://destination-suspecte.example.com',
        ];
    }

    public function test_an_unauthenticated_subject_receives_401_and_creates_no_case(): void
    {
        $campaign = $this->makeCampaign();

        $response = $this->postJson("/advertising/campaigns/{$campaign->id}/reports", $this->validPayload());

        $response->assertStatus(401);
        $this->assertSame(0, ModerationCase::query()->count());
    }

    public function test_any_authenticated_user_unrelated_to_the_advertiser_can_report_any_campaign(): void
    {
        $campaign = $this->makeCampaign();
        $reporter = $this->makeRepresentative();
        $this->grantReport($reporter);

        $response = $this->actingAs($reporter->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/reports", $this->validPayload()
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['moderation_case_id', 'status']);
        $this->assertSame('open', $response->json('status'));

        $case = ModerationCase::query()->findOrFail($response->json('moderation_case_id'));
        $this->assertSame($campaign->id, $case->campaign_id);
        $this->assertSame('high', $case->severity);
    }

    public function test_an_authenticated_subject_without_a_grant_receives_a_safe_403(): void
    {
        $campaign = $this->makeCampaign();
        $reporter = $this->makeRepresentative();

        $response = $this->actingAs($reporter->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/reports", $this->validPayload()
        );

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }

        $this->assertSame(0, ModerationCase::query()->count());
    }

    public function test_a_report_alone_never_changes_campaign_or_campaign_version_state(): void
    {
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);
        $reporter = $this->makeRepresentative();
        $this->grantReport($reporter);

        $response = $this->actingAs($reporter->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/reports", $this->validPayload()
        );

        $response->assertStatus(201);
        $this->assertSame(CampaignState::Active, $campaign->fresh()->state);
        $this->assertSame(CampaignVersionState::Approved, $version->fresh()->state);
    }

    public function test_the_campaign_report_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('campaigns/{campaign}/reports', $webRoutes);
        $this->assertTrue(Route::has('advertising.campaigns.reports.store'));
    }

    public function test_no_ledger_postings_or_transactions_are_produced_by_this_route(): void
    {
        $campaign = $this->makeCampaign();
        $reporter = $this->makeRepresentative();
        $this->grantReport($reporter);

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();
        $postingsBefore = DB::table('ledger.postings')->count();

        $response = $this->actingAs($reporter->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/reports", $this->validPayload()
        );

        $response->assertStatus(201);
        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame($postingsBefore, DB::table('ledger.postings')->count());
    }

    public function test_the_targeted_campaign_id_is_never_taken_from_the_request_body(): void
    {
        $campaign = Campaign::query()->findOrFail($this->makeCampaign()->id);
        $reporter = $this->makeRepresentative();
        $this->grantReport($reporter);

        $response = $this->actingAs($reporter->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/reports", $this->validPayload()
        );

        $response->assertStatus(201);
        $case = ModerationCase::query()->findOrFail($response->json('moderation_case_id'));
        $this->assertSame($campaign->id, $case->campaign_id);
    }
}
