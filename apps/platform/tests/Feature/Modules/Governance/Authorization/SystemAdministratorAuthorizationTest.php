<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Models\User;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Services\SystemAdministratorAuthorizationEngine;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Support\AssuranceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class SystemAdministratorAuthorizationTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, link: PersonAccountLink, grant: Grant}
     */
    private function makeSystemAdministrator(): array
    {
        $user = $this->makeUser('fondateur-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $capability = CapabilityDefinition::query()
            ->where('stable_key', 'governance.system_administrator')
            ->firstOrFail();
        $policy = $this->makePolicy('system-administrator-access-'.Str::uuid());

        $grant = $this->proposeAndActivateGrant(
            subject: $link,
            capability: $capability,
            policy: $policy,
            author: $link,
            approver: null,
            scope: ScopePayload::fromArray(['resource_type' => 'governance.system']),
        );

        return compact('user', 'link', 'grant');
    }

    private function strongAssurance(): AssuranceContext
    {
        return new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Strong,
        );
    }

    public function test_the_container_uses_the_global_system_administrator_engine(): void
    {
        $this->assertInstanceOf(
            SystemAdministratorAuthorizationEngine::class,
            app(AuthorizationEngine::class),
        );
    }

    public function test_a_system_administrator_automatically_receives_a_future_capability_without_an_individual_grant(): void
    {
        ['user' => $user, 'link' => $link] = $this->makeSystemAdministrator();

        $futureCapability = $this->makeCapability(
            stableKey: 'governance.future_configuration',
            minimumSessionAssurance: SessionAssurance::Weak,
            operation: Operation::Read,
            effectiveFrom: now()->subMinute(),
        );

        $this->assertFalse(
            Grant::query()
                ->where('person_account_link_id', $link->id)
                ->where('capability_definition_id', $futureCapability->id)
                ->exists(),
            'Le scénario doit prouver l’accès global sans grant individuel.',
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            user: $user,
            capabilityKey: $futureCapability->stable_key,
            operation: Operation::Read,
            assurance: $this->strongAssurance(),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
        $this->assertSame('system_administrator_override', $result->reason->code);
        $this->assertSame($futureCapability->stable_key, $result->capabilityKey);
    }

    public function test_an_ordinary_user_still_needs_an_individual_grant(): void
    {
        $user = $this->makeUser('ordinaire-'.Str::uuid().'@example.com');
        $futureCapability = $this->makeCapability(
            stableKey: 'governance.future_configuration_for_ordinary_user',
            operation: Operation::Read,
            effectiveFrom: now()->subMinute(),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            user: $user,
            capabilityKey: $futureCapability->stable_key,
            operation: Operation::Read,
            assurance: $this->strongAssurance(),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_the_system_administrator_override_requires_a_strong_session(): void
    {
        ['user' => $user] = $this->makeSystemAdministrator();
        $futureCapability = $this->makeCapability(
            stableKey: 'governance.future_sensitive_screen',
            operation: Operation::Read,
            effectiveFrom: now()->subMinute(),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            user: $user,
            capabilityKey: $futureCapability->stable_key,
            operation: Operation::Read,
        ));

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result->decision);
        $this->assertSame('system_administrator_session_assurance_insufficient', $result->reason->code);
    }

    public function test_the_override_does_not_authorize_an_operation_not_declared_by_the_capability(): void
    {
        ['user' => $user] = $this->makeSystemAdministrator();
        $readCapability = $this->makeCapability(
            stableKey: 'governance.read_only_configuration',
            operation: Operation::Read,
            effectiveFrom: now()->subMinute(),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            user: $user,
            capabilityKey: $readCapability->stable_key,
            operation: Operation::Write,
            assurance: $this->strongAssurance(),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('operation_mismatch', $result->reason->code);
    }

    public function test_a_revoked_system_administrator_immediately_loses_global_access(): void
    {
        ['user' => $user, 'link' => $link, 'grant' => $grant] = $this->makeSystemAdministrator();
        $futureCapability = $this->makeCapability(
            stableKey: 'governance.configuration_after_revocation',
            operation: Operation::Read,
            effectiveFrom: now()->subMinute(),
        );

        app(GrantManager::class)->revoke(
            $grant,
            $link,
            'Retrait du rôle système',
            (string) Str::uuid(),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            user: $user,
            capabilityKey: $futureCapability->stable_key,
            operation: Operation::Read,
            assurance: $this->strongAssurance(),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }
}
