<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Support\AssuranceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthorizationGrantsTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_direct_human_grant_is_allowed(): void
    {
        $user = $this->makeUser('grant-direct@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_grant_via_membership_is_allowed(): void
    {
        $user = $this->makeUser('grant-membership@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization();
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant($membership, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_self_scope_is_allowed_for_own_resource(): void
    {
        $user = $this->makeUser('portee-self@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), scope: ScopePayload::fromArray(['self' => true]));

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_organization_scope_is_allowed_for_matching_organization(): void
    {
        $user = $this->makeUser('portee-organisation@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization();
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant(
            $membership,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['organization_id' => $organization->id]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organization->id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_resource_scope_is_allowed_for_listed_resource(): void
    {
        $user = $this->makeUser('portee-ressource@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['resource_type' => 'invoice', 'resource_ids' => ['invoice-1', 'invoice-2']]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(resourceType: 'invoice', resourceId: 'invoice-2'),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_composite_scope_is_allowed_when_every_dimension_matches(): void
    {
        $user = $this->makeUser('portee-composite@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization();
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant(
            $membership,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray([
                'organization_id' => $organization->id,
                'resource_type' => 'invoice',
                'resource_ids' => ['invoice-1'],
                'country_code' => 'CI',
            ]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(
                organizationId: $organization->id,
                resourceType: 'invoice',
                resourceId: 'invoice-1',
                countryCode: 'CI',
            ),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_read_only_grant_allows_read_and_refuses_write(): void
    {
        $user = $this->makeUser('lecture-seule@example.com');
        $capability = $this->makeCapability('sample.readonly');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::ReadOnly);

        $ownResource = $this->makeResourceContext(ownerPersonId: $link->person_id);

        $readResult = app(AuthorizationEngine::class)->evaluate(
            $this->makeRequest($user, 'sample.readonly', resource: $ownResource)
        );
        $writeResult = app(AuthorizationEngine::class)->evaluate(
            $this->makeRequest($user, 'sample.readonly', resource: $ownResource, operation: Operation::Write)
        );

        $this->assertSame(AuthorizationDecision::AllowedReadOnly, $readResult->decision);
        $this->assertSame(AuthorizationDecision::Denied, $writeResult->decision);
    }

    public function test_masked_grant_returns_explicitly_authorized_fields(): void
    {
        $user = $this->makeUser('masque@example.com');
        $capability = $this->makeCapability('sample.masked');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['self' => true, 'fields' => ['name', 'email']]),
            effect: GrantEffect::Masked,
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.masked',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::AllowedMasked, $result->decision);
        $this->assertSame(['name', 'email'], $result->allowedFields);
    }

    public function test_insufficient_session_assurance_returns_step_up_required(): void
    {
        $user = $this->makeUser('step-up@example.com');
        $capability = $this->makeCapability('sample.strong');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Strong->value]),
        );

        $weakAssurance = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Weak,
        );

        $result = app(AuthorizationEngine::class)->evaluate(
            $this->makeRequest(
                $user,
                'sample.strong',
                resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
                assurance: $weakAssurance,
            )
        );

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result->decision);
    }

    public function test_capability_requiring_approval_never_returns_allowed(): void
    {
        $user = $this->makeUser('approbation@example.com');
        $capability = $this->makeCapability('sample.critical', approvalRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.critical',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::ApprovalRequired, $result->decision);
    }
}
