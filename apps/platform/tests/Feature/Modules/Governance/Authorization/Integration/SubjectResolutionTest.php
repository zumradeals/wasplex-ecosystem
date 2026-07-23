<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Integration\AuthenticatedSubjectResolver;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Modules\Governance\Authorization\AuthorizationTestCase;

/**
 * Résolution du sujet authentifié (P003-B2 §A). Aucun identifiant transmis
 * par le client n'est jamais considéré comme fiable ; absence, contradiction
 * ou inactivité produisent toujours un refus fermé.
 */
class SubjectResolutionTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_account_is_refused(): void
    {
        $this->expectException(SubjectResolutionFailedException::class);

        try {
            app(AuthenticatedSubjectResolver::class)->resolve(null, SessionAssurance::Weak);
        } catch (SubjectResolutionFailedException $exception) {
            $this->assertSame('unauthenticated', $exception->reasonCode);

            throw $exception;
        }
    }

    public function test_account_without_an_active_link_is_refused(): void
    {
        $user = $this->makeUser('integration-no-link@example.com');
        $link = $this->activeLinkFor($user);
        $link->forceFill(['status' => 'superseded'])->save();

        try {
            app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
            $this->fail('La résolution aurait dû être refusée.');
        } catch (SubjectResolutionFailedException $exception) {
            $this->assertSame('no_active_link', $exception->reasonCode);
        }
    }

    public function test_a_membership_belonging_to_another_account_is_refused(): void
    {
        $user = $this->makeUser('integration-membre-1@example.com');
        $otherUser = $this->makeUser('integration-membre-2@example.com');
        $organization = $this->makeOrganization('Organisation Intégration Membre');
        $otherMembership = $this->makeActiveMembership($otherUser, $organization);

        try {
            app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak, $otherMembership->id);
            $this->fail('La résolution aurait dû être refusée.');
        } catch (SubjectResolutionFailedException $exception) {
            $this->assertSame('membership_not_active', $exception->reasonCode);
        }
    }

    public function test_a_suspended_organization_is_refused(): void
    {
        $user = $this->makeUser('integration-org-suspendue@example.com');
        $organization = $this->makeOrganization('Organisation Intégration Suspendue');
        $membership = $this->makeActiveMembership($user, $organization);
        $organization->update(['state' => OrganizationState::Suspended]);

        try {
            app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak, $membership->id);
            $this->fail('La résolution aurait dû être refusée.');
        } catch (SubjectResolutionFailedException $exception) {
            $this->assertSame('organization_not_active', $exception->reasonCode);
        }
    }

    public function test_a_closed_organization_is_refused(): void
    {
        $user = $this->makeUser('integration-org-fermee@example.com');
        $organization = $this->makeOrganization('Organisation Intégration Fermée');
        $membership = $this->makeActiveMembership($user, $organization);
        $organization->update(['state' => OrganizationState::Closed]);

        try {
            app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak, $membership->id);
            $this->fail('La résolution aurait dû être refusée.');
        } catch (SubjectResolutionFailedException $exception) {
            $this->assertSame('organization_not_active', $exception->reasonCode);
        }
    }

    public function test_a_valid_server_context_resolves_to_a_coherent_subject(): void
    {
        $user = $this->makeUser('integration-sujet-coherent@example.com');
        $link = $this->activeLinkFor($user);
        $organization = $this->makeOrganization('Organisation Intégration Cohérente');
        $membership = $this->makeActiveMembership($user, $organization);

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Strong, $membership->id);

        $this->assertSame($user->id, $subject->account->id);
        $this->assertSame($link->id, $subject->personAccountLink->id);
        $this->assertNotNull($subject->membership);
        $this->assertSame($membership->id, $subject->membership->id);
        $this->assertSame(SessionAssurance::Strong, $subject->assurance->sessionAssurance);
    }

    public function test_no_membership_claim_yields_a_subject_without_membership(): void
    {
        $user = $this->makeUser('integration-sans-appartenance@example.com');

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);

        $this->assertNull($subject->membership);
    }
}
