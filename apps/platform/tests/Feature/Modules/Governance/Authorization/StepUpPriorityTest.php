<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Support\AssuranceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Priorité déterministe des décisions (P003-B1.3a). Ordre strict :
 * 1) une insuffisance de contact, identité, unicité, statut organisationnel,
 *    portée, finalité, période, opération ou effet produit un refus ;
 * 2) si tout le reste réussit mais que la session seule est insuffisante,
 *    `step_up_required` ;
 * 3) `approval_required` n'est retourné que lorsque tout, y compris le
 *    niveau de session, est satisfait ;
 * 4) `allowed` seulement si ni step-up ni approbation ne sont requis.
 * Le moteur ne doit jamais ouvrir un processus d'approbation au profit
 * d'une identité insuffisamment qualifiée ou d'une session faible.
 */
class StepUpPriorityTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    private function assuranceWith(
        SessionAssurance $session = SessionAssurance::Weak,
        IdentityAssurance $identity = IdentityAssurance::Undeclared,
        ContactAssurance $contact = ContactAssurance::Unconfirmed,
    ): AssuranceContext {
        return new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: $contact,
            identityAssurance: $identity,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: $session,
        );
    }

    public function test_insufficient_identity_with_approval_required_is_denied(): void
    {
        $user = $this->makeUser('priorite-1@example.com');
        $capability = $this->makeCapability('sample.priorite_identite', approvalRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_identity_assurance' => IdentityAssurance::Verified->value]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.priorite_identite',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            // Session déjà suffisante (weak == plancher par défaut) : seule
            // l'identité manque, ce qui doit rester un refus définitif.
            assurance: $this->assuranceWith(identity: IdentityAssurance::Undeclared),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_insufficient_contact_with_approval_required_is_denied(): void
    {
        $user = $this->makeUser('priorite-2@example.com');
        $capability = $this->makeCapability('sample.priorite_contact', approvalRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_contact_assurance' => ContactAssurance::Confirmed->value]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.priorite_contact',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(contact: ContactAssurance::Unconfirmed),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_insufficient_session_alone_with_approval_required_is_step_up_required(): void
    {
        $user = $this->makeUser('priorite-3@example.com');
        $capability = $this->makeCapability(
            'sample.priorite_session_seule',
            approvalRequired: true,
            minimumSessionAssurance: SessionAssurance::Strong,
        );
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.priorite_session_seule',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(session: SessionAssurance::Weak),
        ));

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result->decision);
    }

    public function test_sufficient_session_with_approval_required_is_approval_required(): void
    {
        $user = $this->makeUser('priorite-4@example.com');
        $capability = $this->makeCapability(
            'sample.priorite_session_ok',
            approvalRequired: true,
            minimumSessionAssurance: SessionAssurance::Weak,
        );
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.priorite_session_ok',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(session: SessionAssurance::Weak),
        ));

        $this->assertSame(AuthorizationDecision::ApprovalRequired, $result->decision);
    }

    public function test_all_conditions_satisfied_without_approval_is_allowed(): void
    {
        $user = $this->makeUser('priorite-5@example.com');
        $capability = $this->makeCapability('sample.priorite_allowed', approvalRequired: false);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.priorite_allowed',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    /**
     * Un grant dont l'effet ne pourrait de toute façon jamais couvrir
     * l'opération demandée ne doit provoquer ni step-up ni approbation,
     * même combiné à une capacité approval_required et une session
     * insuffisante : il est simplement écarté, sans jamais qualifier.
     */
    public function test_incompatible_grant_never_triggers_step_up_or_approval(): void
    {
        $user = $this->makeUser('priorite-6@example.com');
        $capability = $this->makeCapability(
            'sample.priorite_incompatible',
            operation: Operation::Write,
            approvalRequired: true,
            minimumSessionAssurance: SessionAssurance::Strong,
        );
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            effect: GrantEffect::ReadOnly,
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.priorite_incompatible',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Write,
            assurance: $this->assuranceWith(session: SessionAssurance::Weak),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertNotSame(AuthorizationDecision::StepUpRequired, $result->decision);
        $this->assertNotSame(AuthorizationDecision::ApprovalRequired, $result->decision);
    }
}
