<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Models\AuthorizationDecisionRecord;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Provenance de politique (P003-B1.3 §7). `AuthorizationResult::$policyKey`
 * et `$policyVersion` contiennent toujours la clé et la version de la
 * PolicyVersion réellement appliquée — jamais celles de la capacité, qui
 * restent identifiables séparément via `$capabilityKey`/`$capabilityVersion`.
 */
class PolicyProvenanceTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_result_carries_the_real_policy_identity_not_the_capabilitys(): void
    {
        $user = $this->makeUser('provenance-1@example.com');
        $capability = $this->makeCapability('sample.provenance_capability', version: 3);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy('provenance_distinct_policy');

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.provenance_capability',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
        $this->assertSame($policy->stable_key, $result->policyKey);
        $this->assertSame($policy->version, $result->policyVersion);
        $this->assertSame($capability->stable_key, $result->capabilityKey);
        $this->assertSame($capability->version, $result->capabilityVersion);
        $this->assertNotSame($result->policyKey, $result->capabilityKey);
    }

    public function test_the_matched_grant_stays_identifiable_alongside_the_correct_policy(): void
    {
        $user = $this->makeUser('provenance-2@example.com');
        $capability = $this->makeCapability('sample.provenance_matched_grant');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy('provenance_matched_grant_policy');

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.provenance_matched_grant',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame($policy->stable_key, $result->policyKey);

        $matched = null;
        foreach ($result->obligations as $obligation) {
            if ($obligation->type === 'matched_grant') {
                $matched = $obligation;
            }
        }

        $this->assertNotNull($matched);
        $this->assertSame($grant->id, $matched->payload['grant_id']);
    }

    public function test_the_audit_journal_records_the_correct_policy_key_and_version(): void
    {
        $user = $this->makeUser('provenance-3@example.com');
        $capability = $this->makeCapability('sample.provenance_audit');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy('provenance_audit_policy');

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.provenance_audit',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $record = AuthorizationDecisionRecord::query()->latest('created_at')->firstOrFail();

        $this->assertSame($policy->stable_key, $record->policy_key);
        $this->assertSame($policy->version, $record->policy_version);
        $this->assertSame($capability->stable_key, $record->capability_key);
        $this->assertSame($capability->version, $record->capability_version);
        $this->assertNotSame($record->policy_key, $record->capability_key);
    }

    public function test_a_denial_before_any_policy_resolution_carries_no_policy_identity(): void
    {
        $user = $this->makeUser('provenance-4@example.com');

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'nonexistent.capability'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertNull($result->policyKey);
        $this->assertNull($result->policyVersion);
        $this->assertNull($result->capabilityKey);
    }
}
