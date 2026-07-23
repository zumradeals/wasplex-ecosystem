<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\RiskClass;
use App\Modules\Governance\Authorization\Enums\RoleTemplateState;
use App\Modules\Governance\Authorization\Models\RoleTemplate;
use App\Modules\Governance\Authorization\Models\RoleTemplateCapability;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Services\Exceptions\SelfAuthorizationRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SeparationOfDutiesViolationException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleTemplatesAndSeparationTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_a_role_template_alone_authorizes_nothing(): void
    {
        $user = $this->makeUser('role-seul@example.com');
        $capability = $this->makeCapability('sample.read');

        // Le catalogue de capacités est attaché pendant que le rôle est
        // encore à l'état draft : une fois actif, il est figé (P003-B1.1 §4).
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'test_role',
            'version' => 1,
            'label' => 'Rôle de test',
            'description' => 'Rôle modèle de test.',
            'state' => RoleTemplateState::Draft,
        ]);

        RoleTemplateCapability::create([
            'role_template_id' => $roleTemplate->id,
            'capability_definition_id' => $capability->id,
        ]);

        $roleTemplate->update(['state' => RoleTemplateState::Active]);

        // Aucun grant n'a été proposé ni activé : le rôle seul ne produit rien.
        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_a_new_role_template_version_does_not_extend_an_existing_grant(): void
    {
        $user = $this->makeUser('role-version@example.com');
        $capabilityA = $this->makeCapability('sample.read');
        $capabilityB = $this->makeCapability('sample.write');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $author = $this->makeAuthor();

        $roleTemplateV1 = RoleTemplate::create([
            'stable_key' => 'evolving_role',
            'version' => 1,
            'label' => 'Rôle évolutif',
            'description' => 'v1',
            'state' => RoleTemplateState::Draft,
        ]);

        RoleTemplateCapability::create([
            'role_template_id' => $roleTemplateV1->id,
            'capability_definition_id' => $capabilityA->id,
        ]);

        $roleTemplateV1->update(['state' => RoleTemplateState::Active]);

        $grant = app(GrantManager::class)->propose(
            subject: $link,
            capability: $capabilityA,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::RoleTemplate,
            author: $author,
            purpose: null,
            roleTemplate: $roleTemplateV1,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );
        app(GrantManager::class)->activate($grant, $author, null, (string) Str::uuid());

        // Nouvelle version du rôle modèle proposant désormais capabilityB.
        $roleTemplateV1->update(['state' => RoleTemplateState::Retired]);
        $roleTemplateV2 = RoleTemplate::create([
            'stable_key' => 'evolving_role',
            'version' => 2,
            'label' => 'Rôle évolutif',
            'description' => 'v2',
            'state' => RoleTemplateState::Draft,
        ]);
        RoleTemplateCapability::create([
            'role_template_id' => $roleTemplateV2->id,
            'capability_definition_id' => $capabilityB->id,
        ]);
        $roleTemplateV2->update(['state' => RoleTemplateState::Active]);

        // Le grant existant (capabilityA) reste inchangé et ne couvre jamais capabilityB.
        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.write'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_same_author_and_approver_is_refused_for_sensitive_and_critical(): void
    {
        $user = $this->makeUser('meme-acteur@example.com');
        $capability = $this->makeCapability('sample.critical_action', riskClass: RiskClass::Critical);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $author = $this->makeAuthor();

        $grant = app(GrantManager::class)->propose(
            subject: $link,
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
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SeparationOfDutiesViolationException::class);

        app(GrantManager::class)->activate($grant, $author, $author, (string) Str::uuid());
    }

    public function test_sensitive_capability_requires_a_distinct_approver(): void
    {
        $user = $this->makeUser('sensible-sans-approbateur@example.com');
        $capability = $this->makeCapability('sample.sensitive_action', riskClass: RiskClass::Sensitive);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $author = $this->makeAuthor();

        $grant = app(GrantManager::class)->propose(
            subject: $link,
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
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SeparationOfDutiesViolationException::class);

        app(GrantManager::class)->activate($grant, $author, null, (string) Str::uuid());
    }

    public function test_creating_ones_own_entitlement_without_an_approver_is_refused(): void
    {
        $user = $this->makeUser('propre-habilitation@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        // L'auteur EST le sujet du grant, sans approbateur distinct.
        $grant = app(GrantManager::class)->propose(
            subject: $link,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $link,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SelfAuthorizationRefusedException::class);

        app(GrantManager::class)->activate($grant, $link, null, (string) Str::uuid());
    }

    public function test_no_super_admin_capability_or_role_template_exists(): void
    {
        $forbidden = ['admin', 'super_admin', 'god', 'root', 'all', 'any'];

        foreach ($forbidden as $segment) {
            $this->assertCapabilityKeyIsRefused("{$segment}.manage");
            $this->assertCapabilityKeyIsRefused("sample.{$segment}");
        }
    }

    public function test_no_commercial_subscription_name_is_used_as_authorization(): void
    {
        $forbidden = ['premium', 'elite', 'master'];

        foreach ($forbidden as $segment) {
            $this->assertCapabilityKeyIsRefused("sample.{$segment}");
        }
    }

    /**
     * Isolée dans sa propre transaction (savepoint) afin qu'un échec attendu
     * n'invalide pas la transaction englobante du test.
     */
    private function assertCapabilityKeyIsRefused(string $stableKey): void
    {
        try {
            DB::transaction(fn () => $this->makeCapability($stableKey));
            $this->fail("La capacité \"{$stableKey}\" aurait dû être refusée par PostgreSQL.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString(
                'capability_definitions',
                $exception->getMessage(),
                "L'échec attendu pour \"{$stableKey}\" ne provient pas de la contrainte governance.capability_definitions."
            );
        }
    }
}
