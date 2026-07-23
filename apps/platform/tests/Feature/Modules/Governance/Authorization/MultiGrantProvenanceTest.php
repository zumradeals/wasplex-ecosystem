<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Provenance déterministe avec plusieurs grants candidats (TD-0001-B). Pour
 * `step_up_required` et `approval_required`, le grant et la politique
 * retenus dans le résultat ne dépendent jamais de l'ordre de retour SQL :
 * la règle unique est le plus petit UUID parmi les candidats équivalents
 * (documentée sur `AuthorizationEngine::chooseDeterministicCandidate()`).
 */
class MultiGrantProvenanceTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    private function matchedGrantId(AuthorizationResult $result): ?string
    {
        foreach ($result->obligations as $obligation) {
            if ($obligation->type === 'matched_grant') {
                return $obligation->payload['grant_id'];
            }
        }

        return null;
    }

    public function test_step_up_required_provenance_is_deterministic_and_stable_across_repeated_calls(): void
    {
        $user = $this->makeUser('provenance-stepup@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.provenance_stepup', minimumSessionAssurance: SessionAssurance::Strong);
        $policy = $this->makePolicy();

        $grantA = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $grantB = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $request = $this->makeRequest(
            $user,
            'sample.provenance_stepup',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        );

        $result1 = app(AuthorizationEngine::class)->evaluate($request);
        $result2 = app(AuthorizationEngine::class)->evaluate($request);

        $this->assertSame(AuthorizationDecision::StepUpRequired, $result1->decision);
        $this->assertSame(AuthorizationDecision::StepUpRequired, $result2->decision);

        $grantId1 = $this->matchedGrantId($result1);
        $grantId2 = $this->matchedGrantId($result2);

        $this->assertNotNull($grantId1);
        $this->assertSame($grantId1, $grantId2, 'La provenance doit rester identique entre deux appels strictement équivalents.');

        $expected = $grantA->id < $grantB->id ? $grantA->id : $grantB->id;
        $this->assertSame($expected, $grantId1, "Le grant retenu doit toujours être celui dont l'UUID est le plus petit.");
        $this->assertSame($policy->stable_key, $result1->policyKey);
        $this->assertSame($policy->version, $result1->policyVersion);
    }

    public function test_approval_required_provenance_is_deterministic_and_stable_across_repeated_calls(): void
    {
        $user = $this->makeUser('provenance-approval@example.com');
        $link = $this->activeLinkFor($user);
        $capability = $this->makeCapability('sample.provenance_approval', approvalRequired: true);
        $policy = $this->makePolicy();

        $grantA = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $grantB = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $request = $this->makeRequest(
            $user,
            'sample.provenance_approval',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        );

        $result1 = app(AuthorizationEngine::class)->evaluate($request);
        $result2 = app(AuthorizationEngine::class)->evaluate($request);

        $this->assertSame(AuthorizationDecision::ApprovalRequired, $result1->decision);
        $this->assertSame(AuthorizationDecision::ApprovalRequired, $result2->decision);

        $grantId1 = $this->matchedGrantId($result1);
        $grantId2 = $this->matchedGrantId($result2);

        $this->assertNotNull($grantId1);
        $this->assertSame($grantId1, $grantId2);

        $expected = $grantA->id < $grantB->id ? $grantA->id : $grantB->id;
        $this->assertSame($expected, $grantId1);
        $this->assertSame($policy->stable_key, $result1->policyKey);
        $this->assertSame($policy->version, $result1->policyVersion);
    }
}
