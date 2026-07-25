<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Projections\CampaignBudgetProjection;
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
 * Sixième route sensible réelle de l'écosystème (P005-E) : enregistrement
 * d'un encaissement confirmé d'annonceur (ADR-0010 §4 ligne 1 ; ADR-0003
 * §7-8). Première route de tout l'écosystème à produire réellement une
 * écriture Ledger équilibrée.
 */
class CampaignFundingRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function realCapability(): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', 'campaign.fund')
            ->where('state', 'active')
            ->firstOrFail();
    }

    /**
     * `campaign.fund` est `risk_class = critical` : l'activer exige déjà un
     * approbateur distinct de l'auteur de la proposition ET du sujet
     * ({@see GrantManager::activate()}, P003-B3, TD-0001-A) — même défense
     * en profondeur que `campaign.approve`/`campaign.moderate`.
     */
    private function grantFund(PersonAccountLink $subject): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_campaign_fund_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', 'campaign.fund'.random_int(1, PHP_INT_MAX)),
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

        return $manager->activate($grant, $author, $grantApprover, $correlationId);
    }

    /**
     * Reconfirmation mid-session simulée telle que Laravel la constaterait
     * réellement après `password.confirm` (P003-B4) — seul palier `strong`
     * réellement atteignable (TD-0002-A).
     */
    private function withStrongSession(): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_an_unauthenticated_subject_receives_401_and_creates_no_ledger_transaction(): void
    {
        $campaign = $this->makeCampaign();
        $before = DB::table('ledger.ledger_transactions')->count();

        $response = $this->postJson("/advertising/campaigns/{$campaign->id}/funding", [
            'amount' => 10_000,
            'funding_reference' => 'transfer-'.Str::uuid(),
        ]);

        $response->assertStatus(401);
        $this->assertSame($before, DB::table('ledger.ledger_transactions')->count());
    }

    public function test_a_finance_holder_with_a_strong_session_funds_the_campaign(): void
    {
        $campaign = $this->makeCampaign();
        $finance = $this->makeRepresentative();
        $this->grantFund($finance);
        $projection = app(CampaignBudgetProjection::class);

        $response = $this->withStrongSession()->actingAs($finance->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => 'transfer-'.Str::uuid()]
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['campaign_id', 'funding_transaction_id', 'available']);
        $this->assertSame(10_000, $response->json('available'));
        $this->assertSame(10_000, $projection->available($campaign->fresh()));
        $this->assertDatabaseHas('ledger.ledger_transactions', ['type' => 'advertising_campaign_funding']);
    }

    public function test_a_weak_session_receives_step_up_required_and_creates_no_ledger_transaction(): void
    {
        $campaign = $this->makeCampaign();
        $finance = $this->makeRepresentative();
        $this->grantFund($finance);
        $before = DB::table('ledger.ledger_transactions')->count();

        $response = $this->actingAs($finance->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => 'transfer-'.Str::uuid()]
        );

        $response->assertStatus(403);
        $this->assertSame('session_assurance_insufficient', $response->json('reason'));
        $this->assertSame('strong', $response->json('required_session_assurance'));
        $this->assertSame('password_confirmation', $response->json('step_up_action'));
        $this->assertSame($before, DB::table('ledger.ledger_transactions')->count());
    }

    public function test_a_subject_without_a_fund_grant_receives_a_safe_403_even_with_a_strong_session(): void
    {
        $campaign = $this->makeCampaign();
        $subject = $this->makeRepresentative();
        $before = DB::table('ledger.ledger_transactions')->count();

        $response = $this->withStrongSession()->actingAs($subject->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => 'transfer-'.Str::uuid()]
        );

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }

        $this->assertSame($before, DB::table('ledger.ledger_transactions')->count());
    }

    public function test_the_advertisers_own_representative_cannot_self_fund_without_a_grant(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        $response = $this->withStrongSession()->actingAs($representative->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => 'transfer-'.Str::uuid()]
        );

        $response->assertStatus(403);
        $this->assertSame(0, app(CampaignBudgetProjection::class)->available($campaign->fresh()));
    }

    public function test_replaying_the_same_funding_reference_never_double_credits(): void
    {
        $campaign = $this->makeCampaign();
        $finance = $this->makeRepresentative();
        $this->grantFund($finance);
        $projection = app(CampaignBudgetProjection::class);
        $reference = 'transfer-'.Str::uuid();

        $first = $this->withStrongSession()->actingAs($finance->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => $reference]
        );
        $second = $this->withStrongSession()->actingAs($finance->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => $reference]
        );

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('funding_transaction_id'), $second->json('funding_transaction_id'));
        $this->assertSame(10_000, $projection->available($campaign->fresh()));
    }

    public function test_a_conflicting_funding_reference_is_refused_with_a_safe_409(): void
    {
        $campaign = $this->makeCampaign();
        $finance = $this->makeRepresentative();
        $this->grantFund($finance);
        $reference = 'transfer-'.Str::uuid();

        $this->withStrongSession()->actingAs($finance->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 10_000, 'funding_reference' => $reference]
        )->assertStatus(201);

        $conflict = $this->withStrongSession()->actingAs($finance->user)->postJson(
            "/advertising/campaigns/{$campaign->id}/funding",
            ['amount' => 5_000, 'funding_reference' => $reference]
        );

        $conflict->assertStatus(409);
        $this->assertSame('funding_reference_conflict', $conflict->json('reason'));
    }

    public function test_the_campaign_funding_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('campaigns/{campaign}/funding', $webRoutes);
        $this->assertTrue(Route::has('advertising.campaigns.funding.store'));
    }
}
