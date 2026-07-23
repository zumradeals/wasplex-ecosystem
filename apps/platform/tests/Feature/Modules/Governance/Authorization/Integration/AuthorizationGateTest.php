<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubjectResolver;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\ApprovalRequiredException;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationDeniedException;
use App\Modules\Governance\Authorization\Integration\Exceptions\StepUpRequiredException;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Governance\Authorization\AuthorizationTestCase;

/**
 * Point d'application commun (P003-B2 §C). Les décisions `allowed`,
 * `allowed_masked` et `allowed_read_only` sont restituées telles quelles ;
 * `denied`, `step_up_required` et `approval_required` sont toujours
 * distinguées par une exception typée, jamais transformées en autorisation.
 */
class AuthorizationGateTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_allowed_decision_is_returned_to_the_calling_module(): void
    {
        $user = $this->makeUser('gate-allowed@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        $result = app(AuthorizationGate::class)->authorize($request);

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
        $this->assertSame($request->correlationId, $result->correlationId);
    }

    public function test_allowed_masked_decision_preserves_exactly_the_authorized_fields(): void
    {
        $user = $this->makeUser('gate-masked@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.masked');
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['self' => true, 'fields' => ['name', 'email']]),
            effect: GrantEffect::Masked,
        );

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.masked',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        $result = app(AuthorizationGate::class)->authorize($request);

        $this->assertSame(AuthorizationDecision::AllowedMasked, $result->decision);
        $this->assertSame(['name', 'email'], $result->allowedFields);
    }

    public function test_allowed_read_only_decision_never_becomes_a_write(): void
    {
        $user = $this->makeUser('gate-readonly@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.readonly');
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::ReadOnly);

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $ownResource = $this->makeResourceContext(ownerPersonId: $link->person_id);

        $readRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.readonly',
            operation: Operation::Read,
            resource: $ownResource,
            environment: Environment::Testing,
        );
        $readResult = app(AuthorizationGate::class)->authorize($readRequest);
        $this->assertSame(AuthorizationDecision::AllowedReadOnly, $readResult->decision);

        $writeRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.readonly',
            operation: Operation::Write,
            resource: $ownResource,
            environment: Environment::Testing,
        );

        $this->expectException(AuthorizationDeniedException::class);
        app(AuthorizationGate::class)->authorize($writeRequest);
    }

    public function test_denied_decision_is_a_typed_exception_never_silently_allowed(): void
    {
        $user = $this->makeUser('gate-denied@example.com');
        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'nonexistent.capability',
            operation: Operation::Read,
            resource: $this->makeResourceContext(),
            environment: Environment::Testing,
        );

        try {
            app(AuthorizationGate::class)->authorize($request);
            $this->fail('Une exception de refus était attendue.');
        } catch (AuthorizationDeniedException $exception) {
            $this->assertSame(AuthorizationDecision::Denied, $exception->result->decision);
            $this->assertSame($request->correlationId, $exception->result->correlationId);
        }
    }

    public function test_step_up_required_stays_an_elevation_request_without_automatic_execution(): void
    {
        $user = $this->makeUser('gate-stepup@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.strong_gate', minimumSessionAssurance: SessionAssurance::Strong);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.strong_gate',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        try {
            app(AuthorizationGate::class)->authorize($request);
            $this->fail('Une exception de renforcement de session était attendue.');
        } catch (StepUpRequiredException $exception) {
            $this->assertSame(AuthorizationDecision::StepUpRequired, $exception->result->decision);
        }

        // Aucune élévation n'a été exécutée : la session résolue reste weak.
        $this->assertSame(SessionAssurance::Weak, $subject->assurance->sessionAssurance);
    }

    public function test_approval_required_stays_a_pending_approval_without_irreversible_operation(): void
    {
        $user = $this->makeUser('gate-approval@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.critical_gate', approvalRequired: true);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.critical_gate',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
        );

        try {
            app(AuthorizationGate::class)->authorize($request);
            $this->fail("Une exception d'approbation était attendue.");
        } catch (ApprovalRequiredException $exception) {
            $this->assertSame(AuthorizationDecision::ApprovalRequired, $exception->result->decision);
        }
    }

    /**
     * Une organisation revendiquée uniquement via le contexte de la
     * ressource, sans appartenance persistée correspondante, ne remplace
     * jamais l'organisation réellement liée au compte (P003-B2 §A, §4-13).
     */
    public function test_no_client_supplied_organization_ever_replaces_the_persisted_one(): void
    {
        $user = $this->makeUser('gate-org-non-substituee@example.com');
        $link = $this->activeLinkFor($user);
        $organizationA = $this->makeOrganization('Organisation Intégration A');
        $organizationB = $this->makeOrganization('Organisation Intégration B');
        $membership = $this->makeActiveMembership($user, $organizationA);
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $membership,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['organization_id' => $organizationA->id]),
        );

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak, $membership->id);

        // La ressource prétend appartenir à l'organisation B : seule
        // l'appartenance réellement persistée (organisation A) doit compter.
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $this->makeResourceContext(organizationId: $organizationB->id),
            environment: Environment::Testing,
        );

        // Le sujet résolu porte bien l'appartenance persistée (organisation
        // A) : la ressource ne peut donc jamais lui substituer B.
        $this->assertSame($membership->id, $request->membershipId);

        $this->expectException(AuthorizationDeniedException::class);
        app(AuthorizationGate::class)->authorize($request);
    }

    public function test_every_decision_carries_a_correlation_identifier(): void
    {
        $user = $this->makeUser('gate-correlation@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.read');
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);

        $allowedCorrelationId = (string) Str::uuid();
        $deniedCorrelationId = (string) Str::uuid();

        $allowedRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'sample.read',
            operation: Operation::Read,
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            environment: Environment::Testing,
            correlationId: $allowedCorrelationId,
        );
        $allowedResult = app(AuthorizationGate::class)->authorize($allowedRequest);
        $this->assertSame($allowedCorrelationId, $allowedResult->correlationId);

        $deniedRequest = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'nonexistent.capability',
            operation: Operation::Read,
            resource: $this->makeResourceContext(),
            environment: Environment::Testing,
            correlationId: $deniedCorrelationId,
        );

        try {
            app(AuthorizationGate::class)->authorize($deniedRequest);
            $this->fail('Une exception de refus était attendue.');
        } catch (AuthorizationDeniedException $exception) {
            $this->assertSame($deniedCorrelationId, $exception->result->correlationId);
        }
    }

    /**
     * `evaluate()` n'utilise jamais les conditions non pertinentes ; utilisé
     * ici pour vérifier que la non-levée d'exception permet toujours au
     * module appelant de brancher lui-même sur chaque décision.
     */
    public function test_evaluate_never_throws_and_lets_the_caller_branch_explicitly(): void
    {
        $user = $this->makeUser('gate-evaluate@example.com');
        $subject = app(AuthenticatedSubjectResolver::class)->resolve($user, SessionAssurance::Weak);
        $request = app(AuthorizationRequestFactory::class)->make(
            subject: $subject,
            capabilityKey: 'nonexistent.capability',
            operation: Operation::Read,
            resource: $this->makeResourceContext(),
            environment: Environment::Testing,
        );

        $result = app(AuthorizationGate::class)->evaluate($request);

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }
}
