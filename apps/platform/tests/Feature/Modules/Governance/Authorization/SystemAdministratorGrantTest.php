<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Enums\RiskClass;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Services\Exceptions\MultipleSystemAdministratorsRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SelfAuthorizationRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SeparationOfDutiesViolationException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Amendement ADR-0004 2026-07-30 (« Rôle Administrateur Système », décision
 * du fondateur) : un compte détenant un grant actif
 * `governance.system_administrator` est exempté, comme auteur, de la
 * matrice de séparation des tâches de `GrantManager::activate()` pour les
 * octrois qu'il émet — mais son attribution initiale reste soumise à cette
 * même matrice, et un seul compte à la fois peut la détenir.
 */
class SystemAdministratorGrantTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    private function systemAdministratorCapability(): CapabilityDefinition
    {
        return CapabilityDefinition::query()
            ->where('stable_key', 'governance.system_administrator')
            ->firstOrFail();
    }

    public function test_assigning_the_role_itself_still_requires_a_distinct_approver(): void
    {
        $subject = $this->activeLinkFor($this->makeUser('futur-sysadmin-'.Str::uuid().'@example.com'));
        $author = $this->makeAuthor();
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $subject,
            capability: $this->systemAdministratorCapability(),
            policy: $policy,
            scope: ScopePayload::fromArray(['resource_type' => 'governance.system']),
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

        // Personne ne détient encore le rôle : l'exemption ne s'applique
        // pas à sa propre première attribution — même auteur que
        // approbateur reste refusé (risk_class critical).
        $this->expectException(SeparationOfDutiesViolationException::class);

        app(GrantManager::class)->activate($grant, $author, $author, (string) Str::uuid());
    }

    private function makeSystemAdministrator(): PersonAccountLink
    {
        $subject = $this->activeLinkFor($this->makeUser('sysadmin-'.Str::uuid().'@example.com'));
        $author = $this->makeAuthor();
        $approver = $this->makeAuthor();
        $policy = $this->makePolicy('sysadmin-policy-'.Str::uuid());

        $this->proposeAndActivateGrant(
            subject: $subject,
            capability: $this->systemAdministratorCapability(),
            policy: $policy,
            author: $author,
            approver: $approver,
            scope: ScopePayload::fromArray(['resource_type' => 'governance.system']),
        );

        return $subject;
    }

    public function test_once_active_the_system_administrator_can_grant_itself_a_capability_alone(): void
    {
        $sysAdmin = $this->makeSystemAdministrator();
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy('self-grant-policy-'.Str::uuid());

        // Sujet = auteur = l'Administrateur Système lui-même, aucun
        // approbateur — refusé pour tout autre compte (SelfAuthorizationRefusedException).
        $grant = $this->proposeAndActivateGrant(
            subject: $sysAdmin,
            capability: $capability,
            policy: $policy,
            author: $sysAdmin,
            approver: null,
        );

        $this->assertSame(GrantState::Active, $grant->state);
    }

    public function test_the_system_administrator_can_grant_a_critical_capability_to_someone_else_alone(): void
    {
        $sysAdmin = $this->makeSystemAdministrator();
        $beneficiary = $this->activeLinkFor($this->makeUser('beneficiaire-'.Str::uuid().'@example.com'));
        $capability = $this->makeCapability('sample.critical_action', riskClass: RiskClass::Critical);
        $policy = $this->makePolicy('critical-grant-policy-'.Str::uuid());

        // risk_class critical exigerait normalement un approbateur distinct
        // (SeparationOfDutiesViolationException) : l'Administrateur Système
        // en est exempté.
        $grant = $this->proposeAndActivateGrant(
            subject: $beneficiary,
            capability: $capability,
            policy: $policy,
            author: $sysAdmin,
            approver: null,
        );

        $this->assertSame(GrantState::Active, $grant->state);
    }

    public function test_only_one_active_system_administrator_at_a_time(): void
    {
        $this->makeSystemAdministrator();

        $secondSubject = $this->activeLinkFor($this->makeUser('second-sysadmin-'.Str::uuid().'@example.com'));
        $author = $this->makeAuthor();
        $approver = $this->makeAuthor();
        $policy = $this->makePolicy('second-sysadmin-policy-'.Str::uuid());

        $grant = app(GrantManager::class)->propose(
            subject: $secondSubject,
            capability: $this->systemAdministratorCapability(),
            policy: $policy,
            scope: ScopePayload::fromArray(['resource_type' => 'governance.system']),
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

        $this->expectException(MultipleSystemAdministratorsRefusedException::class);

        app(GrantManager::class)->activate($grant, $author, $approver, (string) Str::uuid());
    }

    public function test_a_second_system_administrator_can_be_activated_after_the_first_is_revoked(): void
    {
        $first = $this->makeSystemAdministrator();

        $firstGrant = Grant::query()
            ->where('person_account_link_id', $first->id)
            ->where('capability_definition_id', $this->systemAdministratorCapability()->id)
            ->firstOrFail();

        app(GrantManager::class)->revoke($firstGrant, $this->makeAuthor(), 'Transfert du rôle', (string) Str::uuid());

        $secondSubject = $this->activeLinkFor($this->makeUser('nouveau-sysadmin-'.Str::uuid().'@example.com'));
        $author = $this->makeAuthor();
        $approver = $this->makeAuthor();
        $policy = $this->makePolicy('nouveau-sysadmin-policy-'.Str::uuid());

        $grant = $this->proposeAndActivateGrant(
            subject: $secondSubject,
            capability: $this->systemAdministratorCapability(),
            policy: $policy,
            author: $author,
            approver: $approver,
            scope: ScopePayload::fromArray(['resource_type' => 'governance.system']),
        );

        $this->assertSame(GrantState::Active, $grant->state);
    }

    public function test_a_revoked_system_administrator_loses_the_exemption(): void
    {
        $former = $this->makeSystemAdministrator();

        $formerGrant = Grant::query()
            ->where('person_account_link_id', $former->id)
            ->where('capability_definition_id', $this->systemAdministratorCapability()->id)
            ->firstOrFail();

        app(GrantManager::class)->revoke($formerGrant, $this->makeAuthor(), 'Retrait du rôle', (string) Str::uuid());

        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy('after-revoke-policy-'.Str::uuid());

        $grant = app(GrantManager::class)->propose(
            subject: $former,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $former,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SelfAuthorizationRefusedException::class);

        app(GrantManager::class)->activate($grant, $former, null, (string) Str::uuid());
    }

    public function test_an_ordinary_author_remains_subject_to_normal_separation_of_duties(): void
    {
        // Régression : l'existence de l'exemption ne relâche rien pour un
        // auteur qui ne détient pas governance.system_administrator.
        $subject = $this->activeLinkFor($this->makeUser('sujet-ordinaire-'.Str::uuid().'@example.com'));
        $author = $this->makeAuthor();
        $capability = $this->makeCapability('sample.critical_action_2', riskClass: RiskClass::Critical);
        $policy = $this->makePolicy('ordinaire-policy-'.Str::uuid());

        $grant = app(GrantManager::class)->propose(
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
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SeparationOfDutiesViolationException::class);

        app(GrantManager::class)->activate($grant, $author, null, (string) Str::uuid());
    }
}
