<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Models\CapabilityPurpose;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Périodes d'effet et finalités (P003-B1.3 §5). Le moteur vérifie toujours,
 * à `evaluatedAt` : les périodes d'effet de la capacité, de la politique et
 * de la finalité, ainsi que l'existence actuelle de la liaison
 * capability_purposes lorsque la finalité est requise. Une définition active
 * mais future ou échue est refusée au même titre qu'une définition inconnue.
 */
class EffectivePeriodsTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_a_future_capability_is_denied(): void
    {
        $user = $this->makeUser('periode-1@example.com');
        $capability = $this->makeCapability('sample.periode_future_capability', effectiveFrom: now()->addDay());
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        // L'état reste "active" : seule la période d'effet est hors bornes.
        // GrantManager ne vérifie que l'état à la proposition (P003-B1.3
        // §5) ; c'est bien le moteur, à l'évaluation, qui doit refuser.
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.periode_future_capability'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('unknown_or_inactive_capability', $result->reason->code);
    }

    public function test_an_expired_capability_is_denied(): void
    {
        $user = $this->makeUser('periode-2@example.com');
        $capability = $this->makeCapability(
            'sample.periode_expired_capability',
            effectiveFrom: now()->subDays(2),
            effectiveTo: now()->subDay(),
        );
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.periode_expired_capability'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('unknown_or_inactive_capability', $result->reason->code);
    }

    public function test_a_grant_governed_by_a_future_policy_is_denied(): void
    {
        $user = $this->makeUser('periode-3@example.com');
        $capability = $this->makeCapability('sample.periode_future_policy');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy('periode_future_policy', effectiveFrom: now()->addDay());

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.periode_future_policy'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_a_grant_governed_by_an_expired_policy_is_denied(): void
    {
        $user = $this->makeUser('periode-4@example.com');
        $capability = $this->makeCapability('sample.periode_expired_policy');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy(
            'periode_expired_policy',
            effectiveFrom: now()->subDays(2),
            effectiveTo: now()->subDay(),
        );

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.periode_expired_policy'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_a_future_purpose_is_denied(): void
    {
        $user = $this->makeUser('periode-5@example.com');
        $capability = $this->makeCapability('sample.periode_future_purpose', purposeRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $purpose = $this->makePurpose('periode_future_purpose', effectiveFrom: now()->addDay());
        $this->authorizePurpose($capability, $purpose);

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), purpose: $purpose);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.periode_future_purpose',
            purposeKey: $purpose->stable_key,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_an_expired_purpose_is_denied(): void
    {
        $user = $this->makeUser('periode-6@example.com');
        $capability = $this->makeCapability('sample.periode_expired_purpose', purposeRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $purpose = $this->makePurpose(
            'periode_expired_purpose',
            effectiveFrom: now()->subDays(2),
            effectiveTo: now()->subDay(),
        );
        $this->authorizePurpose($capability, $purpose);

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), purpose: $purpose);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.periode_expired_purpose',
            purposeKey: $purpose->stable_key,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    /**
     * Le grant référence toujours la finalité, mais la liaison
     * capability_purposes n'existe plus au moment de l'évaluation : le
     * moteur vérifie son actualité, pas seulement ce qui a été validé à la
     * proposition (P003-B1.3 §5).
     */
    public function test_purpose_no_longer_linked_to_the_capability_is_denied(): void
    {
        $user = $this->makeUser('periode-7@example.com');
        $capability = $this->makeCapability('sample.periode_purpose_removed', purposeRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $purpose = $this->makePurpose('periode_removed_purpose');
        $this->authorizePurpose($capability, $purpose);

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), purpose: $purpose);

        // Retrait rétroactif du catalogue, hors flux normal.
        $capability->forceFill(['state' => CapabilityState::Draft])->save();
        CapabilityPurpose::query()
            ->where('capability_definition_id', $capability->id)
            ->where('purpose_definition_id', $purpose->id)
            ->delete();
        $capability->forceFill(['state' => CapabilityState::Active])->save();

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.periode_purpose_removed',
            purposeKey: $purpose->stable_key,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_capability_purposes_cannot_be_modified_while_the_capability_is_active(): void
    {
        $capability = $this->makeCapability('sample.periode_frozen_catalog');
        $purpose = $this->makePurpose('periode_frozen_catalog_purpose');

        $this->expectException(QueryException::class);

        CapabilityPurpose::create([
            'capability_definition_id' => $capability->id,
            'purpose_definition_id' => $purpose->id,
        ]);
    }
}
