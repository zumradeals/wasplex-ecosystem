<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Services\GrantManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class RevocationTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_revocation_is_immediately_observed_by_the_next_evaluation(): void
    {
        $user = $this->makeUser('revocation-immediate@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $ownResource = $this->makeResourceContext(ownerPersonId: $link->person_id);

        $before = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read', resource: $ownResource));
        $this->assertSame(AuthorizationDecision::Allowed, $before->decision);

        app(GrantManager::class)->revoke($grant, $link, 'test', (string) Str::uuid());

        $after = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read', resource: $ownResource));
        $this->assertSame(AuthorizationDecision::Denied, $after->decision);
    }

    public function test_expired_grant_is_refused_without_a_scheduled_task_marking_it_expired(): void
    {
        $user = $this->makeUser('expiration-immediate@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $grant->forceFill(['valid_from' => now()->subMinutes(2), 'valid_until' => now()->subMinute()])->save();

        // L'état stocké reste "active" : aucune tâche planifiée n'est intervenue.
        $this->assertSame(GrantState::Active, $grant->fresh()->state);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_a_revoked_grant_cannot_be_reactivated_by_a_simple_update(): void
    {
        $user = $this->makeUser('reactivation-refusee@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        app(GrantManager::class)->revoke($grant, $link, 'test', (string) Str::uuid());

        $this->expectException(QueryException::class);

        $grant->fresh()->forceFill(['state' => GrantState::Active])->save();
    }
}
