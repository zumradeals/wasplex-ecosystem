<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\RiskClass;
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
 * Le niveau de session effectivement exigé est toujours le plus exigeant
 * entre le minimum déclaré par la capacité et celui déclaré par les
 * conditions du grant : un grant ne peut jamais l'abaisser (P003-B1.1 §1).
 */
class SessionAssuranceFloorTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    private function assuranceWith(SessionAssurance $session, IdentityAssurance $identity = IdentityAssurance::Undeclared): AssuranceContext
    {
        return new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: $identity,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: $session,
        );
    }

    public function test_capability_standard_and_session_weak_returns_step_up_required(): void
    {
        $user = $this->makeUser('floor-1@example.com');
        $capability = $this->makeCapability('sample.standard', minimumSessionAssurance: SessionAssurance::Standard);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.standard',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(SessionAssurance::Weak),
        ));

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result->decision);
    }

    public function test_capability_strong_with_weak_condition_and_standard_session_returns_step_up_required(): void
    {
        $user = $this->makeUser('floor-2@example.com');
        $capability = $this->makeCapability('sample.strong_floor', minimumSessionAssurance: SessionAssurance::Strong);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        // Le grant déclare un plancher plus faible (weak) que la capacité :
        // il ne peut jamais abaisser le minimum de la capacité (strong).
        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Weak->value]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.strong_floor',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(SessionAssurance::Standard),
        ));

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result->decision);
    }

    public function test_capability_weak_with_strong_condition_and_standard_session_returns_step_up_required(): void
    {
        $user = $this->makeUser('floor-3@example.com');
        $capability = $this->makeCapability('sample.weak_floor', minimumSessionAssurance: SessionAssurance::Weak);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Strong->value]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.weak_floor',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(SessionAssurance::Standard),
        ));

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result->decision);
    }

    public function test_capability_standard_with_strong_condition_and_strong_session_proceeds_normally(): void
    {
        $user = $this->makeUser('floor-4@example.com');
        $capability = $this->makeCapability('sample.satisfied_floor', minimumSessionAssurance: SessionAssurance::Standard);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Strong->value]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.satisfied_floor',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(SessionAssurance::Strong),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_insufficient_session_with_another_invalid_condition_is_denied_not_step_up(): void
    {
        $user = $this->makeUser('floor-5@example.com');
        $capability = $this->makeCapability('sample.combined_floor', minimumSessionAssurance: SessionAssurance::Weak);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray([
                'minimum_identity_assurance' => IdentityAssurance::Verified->value,
                'minimum_session_assurance' => SessionAssurance::Strong->value,
            ]),
        );

        // Ni l'identité (undeclared < verified), ni la session (weak <
        // strong) ne suffisent : la condition non-session prime et donne un
        // refus définitif, jamais un step_up_required (P003-B1.1 §1).
        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.combined_floor',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(SessionAssurance::Weak, IdentityAssurance::Undeclared),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_no_grant_can_lower_the_capability_minimum(): void
    {
        $user = $this->makeUser('floor-6@example.com');
        $capability = $this->makeCapability('sample.floor_not_lowered', minimumSessionAssurance: SessionAssurance::Standard);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        // Le grant déclare weak, mais le plancher effectif reste standard
        // (celui de la capacité) : une session standard suffit exactement.
        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            conditions: ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Weak->value]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.floor_not_lowered',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            assurance: $this->assuranceWith(SessionAssurance::Standard),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_unknown_session_assurance_value_is_denied(): void
    {
        $user = $this->makeUser('floor-7@example.com');
        $capability = $this->makeCapability('sample.unknown_floor', minimumSessionAssurance: SessionAssurance::Weak, riskClass: RiskClass::Ordinary);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $author = $this->makeAuthor();
        // Version de schéma de conditions inconnue : un grant sémantiquement
        // figé après création (P003-B1.3 §4) est directement inséré ainsi.
        $this->insertRawGrant($link, $capability, $policy, $author, ['conditions_schema_version' => 99]);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.unknown_floor',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }
}
