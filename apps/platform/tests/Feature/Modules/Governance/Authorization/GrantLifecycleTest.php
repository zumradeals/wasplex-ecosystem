<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Services\Exceptions\GrantNotProposedException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Immutabilité sémantique et machine d'états explicite des grants
 * (P003-B1.3 §4). Après création, aucun champ substantiel n'est modifiable ;
 * seules les transitions d'état légitimes sont permises :
 * proposed -> active|revoked|expired, active -> suspended|revoked|expired,
 * suspended -> revoked|expired. revoked et expired sont terminaux ; aucune
 * réactivation suspended -> active ni activation répétée active -> active.
 */
class GrantLifecycleTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_capability_field_is_refused_direct_mutation(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_1');
        $otherCapability = $this->makeCapability('sample.lifecycle_1b');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-1@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $this->expectException(QueryException::class);

        $grant->forceFill(['capability_definition_id' => $otherCapability->id])->save();
    }

    public function test_scope_payload_is_refused_direct_mutation(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_2');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-2@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $this->expectException(QueryException::class);

        $grant->forceFill(['scope_payload' => ['self' => true, 'resource_type' => 'invoice']])->save();
    }

    public function test_effect_is_refused_direct_mutation(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_3');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-3@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $this->expectException(QueryException::class);

        $grant->forceFill(['effect' => GrantEffect::ReadOnly])->save();
    }

    public function test_valid_from_is_refused_direct_mutation(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_4');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-4@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $this->expectException(QueryException::class);

        $grant->forceFill(['valid_from' => now()->addDay()])->save();
    }

    public function test_author_is_refused_direct_mutation(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_5');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-5@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $otherAuthor = $this->makeAuthor();

        $this->expectException(QueryException::class);

        $grant->forceFill(['author_person_account_link_id' => $otherAuthor->id])->save();
    }

    public function test_state_transition_alone_is_permitted(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_6');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-6@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $grant->forceFill(['state' => GrantState::Suspended])->save();

        $this->assertSame(GrantState::Suspended, $grant->fresh()->state);
    }

    public function test_proposed_to_suspended_is_an_invalid_transition(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_7');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-7@example.com'));
        $policy = $this->makePolicy();
        $author = $this->makeAuthor();

        $grant = app(GrantManager::class)->propose(
            subject: $link,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->assertSame(GrantState::Proposed, $grant->state);

        $this->expectException(QueryException::class);

        $grant->forceFill(['state' => GrantState::Suspended])->save();
    }

    public function test_active_to_active_repeated_activation_is_refused_by_database(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_8');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-8@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $this->expectException(QueryException::class);

        DB::table('governance.grants')->where('id', $grant->id)->update(['state' => GrantState::Active->value]);
    }

    public function test_grant_manager_activate_refuses_a_non_proposed_grant(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_9');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-9@example.com'));
        $policy = $this->makePolicy();
        $author = $this->makeAuthor();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $author);

        $this->expectException(GrantNotProposedException::class);

        app(GrantManager::class)->activate($grant->fresh(), $author, null, (string) Str::uuid());
    }

    public function test_suspended_to_active_reactivation_is_refused(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_10');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-10@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        app(GrantManager::class)->suspend($grant, $link, 'test', (string) Str::uuid());

        $this->expectException(QueryException::class);

        $grant->fresh()->forceFill(['state' => GrantState::Active])->save();
    }

    public function test_expired_state_is_terminal(): void
    {
        $capability = $this->makeCapability('sample.lifecycle_11');
        $link = $this->activeLinkFor($this->makeUser('lifecycle-11@example.com'));
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $grant->forceFill(['state' => GrantState::Expired])->save();
        $this->assertSame(GrantState::Expired, $grant->fresh()->state);

        $this->expectException(QueryException::class);

        $grant->fresh()->forceFill(['state' => GrantState::Suspended])->save();
    }
}
