<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Services\Exceptions\SubjectOrganizationMismatchException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Cohérence sujet, appartenance et organisation (P003-B1.1 §2). Une
 * organisation fournie par le client n'est jamais considérée comme une
 * preuve : le moteur résout toujours l'organisation réelle depuis
 * l'appartenance active du compte authentifié.
 */
class OrganizationCoherenceTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_membership_of_organization_a_with_scope_of_organization_b_is_refused_at_proposal(): void
    {
        $user = $this->makeUser('coherence-1@example.com');
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy();
        $organizationA = $this->makeOrganization('Organisation Cohérence A');
        $organizationB = $this->makeOrganization('Organisation Cohérence B');
        $membership = $this->makeActiveMembership($user, $organizationA);

        $this->expectException(SubjectOrganizationMismatchException::class);

        app(GrantManager::class)->propose(
            subject: $membership,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['organization_id' => $organizationB->id]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $this->makeAuthor(),
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_individual_link_subject_with_an_organization_scope_is_refused_at_proposal(): void
    {
        $user = $this->makeUser('coherence-1b@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization('Organisation Cohérence 1b');

        $this->expectException(SubjectOrganizationMismatchException::class);

        app(GrantManager::class)->propose(
            subject: $link,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['organization_id' => $organization->id]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $this->makeAuthor(),
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_grant_of_organization_a_with_request_scope_of_organization_b_is_refused(): void
    {
        $user = $this->makeUser('coherence-2@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organizationA = $this->makeOrganization('Organisation Cohérence 2A');
        $organizationB = $this->makeOrganization('Organisation Cohérence 2B');
        $membership = $this->makeActiveMembership($user, $organizationA);

        $this->proposeAndActivateGrant(
            $membership,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['organization_id' => $organizationA->id]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organizationB->id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_request_carrying_another_persons_person_account_link_is_refused(): void
    {
        $user = $this->makeUser('coherence-3@example.com');
        $otherUser = $this->makeUser('coherence-3-other@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $otherLink = PersonAccountLink::query()->where('user_id', $otherUser->id)->where('status', 'active')->firstOrFail();
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        // La requête prétend agir pour $user (accountUserId) mais fournit la
        // liaison d'un AUTRE compte : le moteur doit refuser cette
        // incohérence plutôt que de faire confiance à la liaison fournie.
        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            personAccountLinkId: $otherLink->id,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('account_link_not_active', $result->reason->code);
    }

    public function test_suspended_membership_is_refused(): void
    {
        $user = $this->makeUser('coherence-4a@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization('Organisation Cohérence 4A');
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant($membership, $capability, $policy, $this->makeAuthor());
        $membership->update(['status' => 'suspended']);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organization->id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('membership_not_active', $result->reason->code);
    }

    public function test_revoked_membership_is_refused(): void
    {
        $user = $this->makeUser('coherence-4b@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization('Organisation Cohérence 4B');
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant($membership, $capability, $policy, $this->makeAuthor());
        $membership->update(['status' => 'revoked']);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organization->id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('membership_not_active', $result->reason->code);
    }

    public function test_closed_organization_is_refused(): void
    {
        $user = $this->makeUser('coherence-5a@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization('Organisation Cohérence 5A');
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant($membership, $capability, $policy, $this->makeAuthor());
        $organization->update(['state' => OrganizationState::Closed]);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organization->id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('organization_not_active', $result->reason->code);
    }

    public function test_suspended_organization_is_refused(): void
    {
        $user = $this->makeUser('coherence-5b@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization('Organisation Cohérence 5B');
        $membership = $this->makeActiveMembership($user, $organization);

        $this->proposeAndActivateGrant($membership, $capability, $policy, $this->makeAuthor());
        $organization->update(['state' => OrganizationState::Suspended]);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organization->id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('organization_not_active', $result->reason->code);
    }

    public function test_coherent_case_is_always_authorizable(): void
    {
        $user = $this->makeUser('coherence-6@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organization = $this->makeOrganization('Organisation Cohérence 6');
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
}
