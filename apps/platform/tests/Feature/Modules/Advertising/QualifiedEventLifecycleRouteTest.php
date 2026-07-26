<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
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
 * Septième à neuvième routes sensibles réelles de l'écosystème (P005-F) :
 * cycle de vie complet d'un QualifiedEvent (ADR-0010 §4 lignes 3-5).
 * Réservées au personnel Wasplex anti-fraude/mesure d'attention, jamais au
 * bénéficiaire lui-même — voir le raisonnement documenté sur chaque
 * capacité.
 */
class QualifiedEventLifecycleRouteTest extends AdvertisingTestCase
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
     * `event.accept` est `risk_class = critical` : l'activer exige déjà un
     * approbateur distinct de l'auteur de la proposition ET du sujet
     * ({@see GrantManager::activate()}, P003-B3, TD-0001-A) — même défense
     * en profondeur que `campaign.fund`. `event.submit`/`event.reject`
     * sont `sensitive` et suivent la même règle (approbateur distinct
     * exigé dès `sensitive`).
     */
    private function grant(string $stableKey, PersonAccountLink $subject, string $resourceType): Grant
    {
        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_'.str_replace('.', '_', $stableKey).'_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', $stableKey.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));
        $grantApprover = $this->activeLinkFor($this->makeUser('grant-approver-'.Str::uuid().'@example.com'));

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $this->capability($stableKey),
            policy: $policy,
            scope: ScopePayload::fromArray(['resource_type' => $resourceType]),
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

    private function grantSubmit(PersonAccountLink $subject): Grant
    {
        return $this->grant('event.submit', $subject, 'advertising.campaign');
    }

    private function grantAccept(PersonAccountLink $subject): Grant
    {
        return $this->grant('event.accept', $subject, 'advertising.qualified_event');
    }

    private function grantReject(PersonAccountLink $subject): Grant
    {
        return $this->grant('event.reject', $subject, 'advertising.qualified_event');
    }

    private function withStrongSession(): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validSubmitPayload(): array
    {
        return [
            'beneficiary_person_account_link_id' => $this->makeBeneficiary()->id,
            'format' => 'banner',
            'evidence' => ['proof' => 'completion'],
            'applied_price_amount' => 3_000,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    private function fundedApprovedCampaign(int $amount = 10_000): array
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, $amount);
        $version = $this->proposeAndApproveVersion($campaign);

        return [$campaign, $version];
    }

    // --- event.submit -------------------------------------------------

    public function test_submit_an_unauthenticated_subject_receives_401_and_creates_no_event(): void
    {
        [, $version] = $this->fundedApprovedCampaign();

        $response = $this->postJson(
            "/advertising/campaign-versions/{$version->id}/qualified-events",
            $this->validSubmitPayload()
        );

        $response->assertStatus(401);
        $this->assertSame(0, QualifiedEvent::query()->count());
    }

    public function test_submit_a_grant_holder_reserves_the_cost_and_creates_a_pending_event(): void
    {
        [$campaign, $version] = $this->fundedApprovedCampaign();
        $staff = $this->makeRepresentative();
        $this->grantSubmit($staff);
        $projection = app(CampaignBudgetProjection::class);

        $response = $this->actingAs($staff->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/qualified-events",
            $this->validSubmitPayload()
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['qualified_event_id', 'billing_status']);
        $this->assertSame('pending', $response->json('billing_status'));
        $this->assertSame(7_000, $projection->available($campaign->fresh()));
        $this->assertSame(3_000, $projection->reserved($campaign->fresh()));
        $this->assertDatabaseHas('ledger.ledger_transactions', ['type' => 'advertising_campaign_reservation']);
    }

    public function test_submit_a_non_approved_version_is_refused_with_a_safe_409(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $sector = $this->makeSectorClassification();
        $version = app(CampaignVersionService::class)->propose(
            campaign: $campaign,
            sector: $sector,
            creations: ['headline' => 'Titre de test'],
            expectedEvent: ['format' => 'banner', 'condition' => 'completion'],
            destination: ['url' => 'https://annonceur.example.com'],
            territory: ['CI'],
            author: $this->makeRepresentative(),
        );
        $staff = $this->makeRepresentative();
        $this->grantSubmit($staff);

        $response = $this->actingAs($staff->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/qualified-events",
            $this->validSubmitPayload()
        );

        $response->assertStatus(409);
        $this->assertSame('campaign_version_not_approved', $response->json('reason'));
        $this->assertSame(0, QualifiedEvent::query()->count());
    }

    public function test_submit_a_subject_without_a_grant_receives_a_safe_403(): void
    {
        [, $version] = $this->fundedApprovedCampaign();
        $subject = $this->makeRepresentative();

        $response = $this->actingAs($subject->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/qualified-events",
            $this->validSubmitPayload()
        );

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }

        $this->assertSame(0, QualifiedEvent::query()->count());
    }

    public function test_submit_insufficient_budget_is_refused_with_a_safe_409(): void
    {
        [, $version] = $this->fundedApprovedCampaign(1_000);
        $staff = $this->makeRepresentative();
        $this->grantSubmit($staff);

        $payload = $this->validSubmitPayload();
        $payload['applied_price_amount'] = 1_001;

        $response = $this->actingAs($staff->user)->postJson(
            "/advertising/campaign-versions/{$version->id}/qualified-events",
            $payload
        );

        $response->assertStatus(409);
        $this->assertSame('insufficient_budget', $response->json('reason'));
    }

    public function test_submit_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('campaign-versions/{campaignVersion}/qualified-events', $webRoutes);
        $this->assertTrue(Route::has('advertising.qualified-events.store'));
    }

    // --- event.accept ---------------------------------------------------

    /**
     * Construit le QualifiedEvent `Pending` directement via le service
     * (jamais via la route HTTP) : `actingAs()` fixe l'utilisateur courant
     * pour tout le reste du test, y compris les requêtes non authentifiées
     * suivantes — passer par la route ici ferait fuiter une authentification
     * dans les tests `_unauthenticated_` de ce bloc.
     */
    private function pendingEvent(int $amount = 3_000): QualifiedEvent
    {
        [$campaign, $version] = $this->fundedApprovedCampaign();
        $beneficiary = $this->makeBeneficiary();

        return $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $beneficiary,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: $amount,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_accept_an_unauthenticated_subject_receives_401_and_leaves_the_event_pending(): void
    {
        $event = $this->pendingEvent();

        $response = $this->postJson("/advertising/qualified-events/{$event->id}/accept");

        $response->assertStatus(401);
        $this->assertSame(BillingStatus::Pending, $event->fresh()->billing_status);
    }

    public function test_accept_a_grant_holder_with_a_strong_session_validates_and_splits_fifty_fifty(): void
    {
        $event = $this->pendingEvent(4_000);
        $staff = $this->makeRepresentative();
        $this->grantAccept($staff);

        $response = $this->withStrongSession()->actingAs($staff->user)->postJson(
            "/advertising/qualified-events/{$event->id}/accept"
        );

        $response->assertStatus(200);
        $this->assertSame('accepted', $response->json('billing_status'));
        // Décision humaine tracée comme telle, requêtable (arbitrage
        // Koné/SIRR 2026-07-26) — jamais confondue avec une acceptation
        // automatique, qui exigerait une référence de règles épinglée.
        $this->assertSame('manual', $response->json('acceptance_mode'));
        $fresh = $event->fresh();
        $this->assertSame(BillingStatus::Accepted, $fresh->billing_status);
        $this->assertSame('manual', $fresh->acceptance_mode?->value);
        $this->assertNull($fresh->acceptance_rules_configuration_key);
        $this->assertDatabaseHas('ledger.ledger_transactions', ['type' => 'advertising_campaign_distribution']);
    }

    public function test_accept_a_weak_session_receives_step_up_required_and_leaves_the_event_pending(): void
    {
        $event = $this->pendingEvent();
        $staff = $this->makeRepresentative();
        $this->grantAccept($staff);

        $response = $this->actingAs($staff->user)->postJson(
            "/advertising/qualified-events/{$event->id}/accept"
        );

        $response->assertStatus(403);
        $this->assertSame('session_assurance_insufficient', $response->json('reason'));
        $this->assertSame('strong', $response->json('required_session_assurance'));
        $this->assertSame(BillingStatus::Pending, $event->fresh()->billing_status);
    }

    public function test_accept_a_subject_without_a_grant_receives_a_safe_403(): void
    {
        $event = $this->pendingEvent();
        $subject = $this->makeRepresentative();

        $response = $this->withStrongSession()->actingAs($subject->user)->postJson(
            "/advertising/qualified-events/{$event->id}/accept"
        );

        $response->assertStatus(403);
        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }
        $this->assertSame(BillingStatus::Pending, $event->fresh()->billing_status);
    }

    public function test_accept_replaying_an_already_accepted_event_has_no_second_effect(): void
    {
        $event = $this->pendingEvent(4_000);
        $staff = $this->makeRepresentative();
        $this->grantAccept($staff);

        $this->withStrongSession()->actingAs($staff->user)->postJson(
            "/advertising/qualified-events/{$event->id}/accept"
        )->assertStatus(200);

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();

        $second = $this->withStrongSession()->actingAs($staff->user)->postJson(
            "/advertising/qualified-events/{$event->id}/accept"
        );

        $second->assertStatus(200);
        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
    }

    public function test_accept_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('qualified-events/{qualifiedEvent}/accept', $webRoutes);
        $this->assertTrue(Route::has('advertising.qualified-events.accept'));
    }

    // --- event.reject ---------------------------------------------------

    public function test_reject_an_unauthenticated_subject_receives_401_and_leaves_the_event_pending(): void
    {
        $event = $this->pendingEvent();

        $response = $this->postJson("/advertising/qualified-events/{$event->id}/reject", ['reason' => 'Preuve invalide']);

        $response->assertStatus(401);
        $this->assertSame(BillingStatus::Pending, $event->fresh()->billing_status);
    }

    public function test_reject_a_grant_holder_releases_the_reservation_back_to_available(): void
    {
        $event = $this->pendingEvent(2_500);
        $campaign = $event->campaign;
        $staff = $this->makeRepresentative();
        $this->grantReject($staff);
        $projection = app(CampaignBudgetProjection::class);

        $response = $this->actingAs($staff->user)->postJson(
            "/advertising/qualified-events/{$event->id}/reject",
            ['reason' => 'Preuve invalide']
        );

        $response->assertStatus(200);
        $this->assertSame('rejected', $response->json('billing_status'));
        $this->assertSame(10_000, $projection->available($campaign->fresh()));
        $this->assertSame(0, $projection->reserved($campaign->fresh()));
    }

    public function test_reject_without_a_reason_is_rejected_with_a_422(): void
    {
        $event = $this->pendingEvent();
        $staff = $this->makeRepresentative();
        $this->grantReject($staff);

        $response = $this->actingAs($staff->user)->postJson(
            "/advertising/qualified-events/{$event->id}/reject",
            []
        );

        $response->assertStatus(422);
        $this->assertSame(BillingStatus::Pending, $event->fresh()->billing_status);
    }

    public function test_reject_a_subject_without_a_grant_receives_a_safe_403(): void
    {
        $event = $this->pendingEvent();
        $subject = $this->makeRepresentative();

        $response = $this->actingAs($subject->user)->postJson(
            "/advertising/qualified-events/{$event->id}/reject",
            ['reason' => 'Preuve invalide']
        );

        $response->assertStatus(403);
        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }
        $this->assertSame(BillingStatus::Pending, $event->fresh()->billing_status);
    }

    public function test_reject_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('qualified-events/{qualifiedEvent}/reject', $webRoutes);
        $this->assertTrue(Route::has('advertising.qualified-events.reject'));
    }
}
