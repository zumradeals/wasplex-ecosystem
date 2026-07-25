<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Models\ModerationCase;
use App\Modules\Advertising\Services\ModerationService;
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
 * Cinquième route sensible réelle de l'écosystème (P005-D) : décision de
 * modération sur un `ModerationCase` déjà ouvert (`03-...md` §2 ;
 * ADR-0010 §3), avec suspension réelle de la Campaign lorsque la mesure
 * conservatoire `campaign_suspended` est appliquée.
 */
class ModerationDecisionRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function realCapability(): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', 'campaign.moderate')
            ->where('state', 'active')
            ->firstOrFail();
    }

    /**
     * `campaign.moderate` est `risk_class = sensitive` : l'activer exige déjà
     * un approbateur distinct de l'auteur de la proposition ET du sujet
     * ({@see GrantManager::activate()}, P003-B3, TD-0001-A) — même défense en
     * profondeur que `campaign.approve` (P005-C).
     */
    private function grantModerate(PersonAccountLink $subject): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_campaign_moderate_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'campaign.moderate'.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));
        $grantApprover = $this->activeLinkFor($this->makeUser('grant-approver-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $this->realCapability(),
            policy: $policy,
            scope: ScopePayload::fromArray(['resource_type' => 'advertising.moderation_case']),
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

    private function openCase(): ModerationCase
    {
        $campaign = $this->makeCampaign();

        return app(ModerationService::class)->openCase($campaign, 'Destination non déclarée', 'high');
    }

    public function test_an_unauthenticated_subject_receives_401_and_leaves_the_case_untouched(): void
    {
        $case = $this->openCase();

        $response = $this->postJson("/advertising/moderation-cases/{$case->id}/decisions", [
            'decision' => 'violation_confirmed',
        ]);

        $response->assertStatus(401);
        $this->assertSame('open', $case->fresh()->status->value);
    }

    public function test_a_decision_with_campaign_suspended_actually_suspends_the_campaign(): void
    {
        $case = $this->openCase();
        $moderator = $this->makeRepresentative();
        $this->grantModerate($moderator);

        $response = $this->actingAs($moderator->user)->postJson(
            "/advertising/moderation-cases/{$case->id}/decisions",
            ['decision' => 'violation_confirmed', 'precautionary_measure' => 'campaign_suspended']
        );

        $response->assertStatus(200);
        $this->assertSame('resolved', $response->json('status'));
        $this->assertSame('violation_confirmed', $response->json('decision'));
        $this->assertSame('campaign_suspended', $response->json('precautionary_measure'));
        $this->assertSame('suspended', $response->json('campaign_state'));

        $this->assertSame(CampaignState::Suspended, $case->fresh()->campaign->fresh()->state);
        $this->assertSame('resolved', $case->fresh()->status->value);
    }

    public function test_a_decision_without_a_precautionary_measure_never_changes_the_campaign_state(): void
    {
        $case = $this->openCase();
        $moderator = $this->makeRepresentative();
        $this->grantModerate($moderator);

        $response = $this->actingAs($moderator->user)->postJson(
            "/advertising/moderation-cases/{$case->id}/decisions",
            ['decision' => 'no_violation_found']
        );

        $response->assertStatus(200);
        $this->assertSame('resolved', $response->json('status'));
        $this->assertSame('none', $response->json('precautionary_measure'));
        $this->assertSame('active', $response->json('campaign_state'));

        $this->assertSame(CampaignState::Active, $case->fresh()->campaign->fresh()->state);
    }

    public function test_a_third_party_without_a_moderate_grant_receives_a_safe_403(): void
    {
        $case = $this->openCase();
        $subject = $this->makeRepresentative();

        $response = $this->actingAs($subject->user)->postJson(
            "/advertising/moderation-cases/{$case->id}/decisions",
            ['decision' => 'violation_confirmed']
        );

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }

        $this->assertSame('open', $case->fresh()->status->value);
        $this->assertSame(CampaignState::Active, $case->fresh()->campaign->fresh()->state);
    }

    public function test_the_moderation_decision_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('moderation-cases/{moderationCase}/decisions', $webRoutes);
        $this->assertTrue(Route::has('advertising.moderation-cases.decisions.store'));
    }

    public function test_no_ledger_postings_or_transactions_are_produced_by_this_route(): void
    {
        $case = $this->openCase();
        $moderator = $this->makeRepresentative();
        $this->grantModerate($moderator);

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();
        $postingsBefore = DB::table('ledger.postings')->count();

        $response = $this->actingAs($moderator->user)->postJson(
            "/advertising/moderation-cases/{$case->id}/decisions",
            ['decision' => 'violation_confirmed', 'precautionary_measure' => 'campaign_suspended']
        );

        $response->assertStatus(200);
        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame($postingsBefore, DB::table('ledger.postings')->count());
    }
}
