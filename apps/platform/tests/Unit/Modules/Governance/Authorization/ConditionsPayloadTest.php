<?php

namespace Tests\Unit\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Support\ConditionsMatcher;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\InvalidConditionsPayloadException;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Support\AssuranceContext;
use PHPUnit\Framework\TestCase;

class ConditionsPayloadTest extends TestCase
{
    public function test_an_empty_payload_is_valid_and_imposes_no_extra_condition(): void
    {
        $conditions = ConditionsPayload::fromArray([]);

        $this->assertNull($conditions->minimumSessionAssurance);
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $this->expectException(InvalidConditionsPayloadException::class);

        ConditionsPayload::fromArray(['unknown_condition' => 'value']);
    }

    public function test_an_unknown_value_is_rejected(): void
    {
        $this->expectException(InvalidConditionsPayloadException::class);

        ConditionsPayload::fromArray(['minimum_session_assurance' => 'not_a_real_level']);
    }

    public function test_an_unknown_schema_version_is_rejected(): void
    {
        $this->expectException(InvalidConditionsPayloadException::class);

        ConditionsPayload::fromStored(99, []);
    }

    public function test_an_absent_level_is_not_treated_as_implicitly_maximal(): void
    {
        $conditions = ConditionsPayload::fromArray(['minimum_identity_assurance' => IdentityAssurance::Declared->value]);

        $assurance = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Declared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Weak,
        );

        // Aucune contrainte sur uniqueness/contact/session : leur niveau
        // faible ne doit jamais faire échouer une condition qui ne les
        // mentionne pas.
        $result = (new ConditionsMatcher)->evaluate($conditions, $assurance, SessionAssurance::Weak);

        $this->assertTrue($result->satisfied);
    }

    public function test_insufficient_non_session_assurance_is_a_plain_denial(): void
    {
        $conditions = ConditionsPayload::fromArray(['minimum_identity_assurance' => IdentityAssurance::Verified->value]);

        $assurance = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Strong,
        );

        $result = (new ConditionsMatcher)->evaluate($conditions, $assurance, SessionAssurance::Weak);

        $this->assertFalse($result->satisfied);
        $this->assertFalse($result->onlySessionAssuranceInsufficient);
    }

    public function test_insufficient_session_assurance_alone_is_distinguished(): void
    {
        $conditions = ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Strong->value]);

        $assurance = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Confirmed,
            identityAssurance: IdentityAssurance::Verified,
            uniquenessAssurance: UniquenessAssurance::Sufficient,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Weak,
        );

        $result = (new ConditionsMatcher)->evaluate($conditions, $assurance, SessionAssurance::Weak);

        $this->assertFalse($result->satisfied);
        $this->assertTrue($result->onlySessionAssuranceInsufficient);
    }

    public function test_disputed_uniqueness_never_satisfies_a_minimum_requirement(): void
    {
        $conditions = ConditionsPayload::fromArray(['minimum_uniqueness_assurance' => UniquenessAssurance::Unknown->value]);

        $assurance = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Disputed,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Weak,
        );

        $result = (new ConditionsMatcher)->evaluate($conditions, $assurance, SessionAssurance::Weak);

        $this->assertFalse($result->satisfied);
    }

    public function test_a_grant_condition_can_never_lower_the_capability_floor(): void
    {
        // Capacité strong (paramètre du test) + condition weak : le grant ne
        // peut jamais abaisser le plancher de la capacité (P003-B1.1 §1).
        $conditions = ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Weak->value]);

        $standardSession = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Standard,
        );

        $result = (new ConditionsMatcher)->evaluate($conditions, $standardSession, SessionAssurance::Strong);

        // Le plancher effectif est strong (capacité) malgré la condition
        // weak du grant : une session standard reste insuffisante.
        $this->assertFalse($result->satisfied);
        $this->assertTrue($result->onlySessionAssuranceInsufficient);
    }

    public function test_a_grant_condition_can_raise_the_floor_above_the_capability_minimum(): void
    {
        $conditions = ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Strong->value]);

        $standardSession = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Standard,
        );

        $result = (new ConditionsMatcher)->evaluate($conditions, $standardSession, SessionAssurance::Weak);

        // Le plancher effectif est strong (condition), malgré une capacité
        // faible : une session standard reste insuffisante.
        $this->assertFalse($result->satisfied);
        $this->assertTrue($result->onlySessionAssuranceInsufficient);
    }

    public function test_meeting_the_effective_floor_exactly_is_satisfied(): void
    {
        $conditions = ConditionsPayload::fromArray(['minimum_session_assurance' => SessionAssurance::Strong->value]);

        $strongSession = new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Strong,
        );

        $result = (new ConditionsMatcher)->evaluate($conditions, $strongSession, SessionAssurance::Standard);

        $this->assertTrue($result->satisfied);
    }
}
