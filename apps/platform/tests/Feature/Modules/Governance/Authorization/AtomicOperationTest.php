<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Opération atomique de la capacité (P003-B1.3 §3) : une capacité de
 * lecture ne peut jamais autoriser une écriture, et réciproquement. Un
 * export exige une capacité explicitement dédiée. Aucun effet de grant,
 * y compris `allow`, ne contourne cette barrière posée avant même
 * l'évaluation des grants.
 */
class AtomicOperationTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_read_capability_denies_a_write_request(): void
    {
        $user = $this->makeUser('operation-1@example.com');
        $capability = $this->makeCapability('sample.op_read', operation: Operation::Read);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.op_read',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Write,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('operation_mismatch', $result->reason->code);
    }

    public function test_write_capability_denies_a_read_request(): void
    {
        $user = $this->makeUser('operation-2@example.com');
        $capability = $this->makeCapability('sample.op_write', operation: Operation::Write);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.op_write',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Read,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('operation_mismatch', $result->reason->code);
    }

    public function test_export_capability_with_export_request_behaves_as_expected(): void
    {
        $user = $this->makeUser('operation-3@example.com');
        $capability = $this->makeCapability('sample.op_export', operation: Operation::Export);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.op_export',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Export,
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_export_capability_denies_a_read_request(): void
    {
        $user = $this->makeUser('operation-4@example.com');
        $capability = $this->makeCapability('sample.op_export_only', operation: Operation::Export);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.op_export_only',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Read,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('operation_mismatch', $result->reason->code);
    }

    /**
     * Un effet `allow`, pourtant le plus permissif, ne contourne jamais
     * l'opération déclarée par la capacité (P003-B1.3 §3).
     */
    public function test_allow_effect_never_bypasses_the_declared_operation(): void
    {
        $user = $this->makeUser('operation-5@example.com');
        $capability = $this->makeCapability('sample.op_allow_bounded', operation: Operation::Read);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $readResult = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.op_allow_bounded',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Read,
        ));
        $writeResult = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.op_allow_bounded',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            operation: Operation::Write,
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $readResult->decision);
        $this->assertSame(AuthorizationDecision::Denied, $writeResult->decision);
        $this->assertSame('operation_mismatch', $writeResult->reason->code);
    }

    public function test_capability_operation_is_immutable_once_active(): void
    {
        $capability = $this->makeCapability('sample.op_immutable', operation: Operation::Read);

        $this->expectException(QueryException::class);

        $capability->forceFill(['operation' => Operation::Write])->save();
    }
}
