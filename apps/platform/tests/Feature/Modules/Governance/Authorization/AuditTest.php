<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Models\AuthorizationDecisionRecord;
use App\Modules\Governance\Authorization\Models\GrantEvent;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_every_decision_produces_a_trace(): void
    {
        $user = $this->makeUser('trace-decision@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $countBefore = AuthorizationDecisionRecord::query()->count();

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
        $this->assertSame($countBefore + 1, AuthorizationDecisionRecord::query()->count());

        $record = AuthorizationDecisionRecord::query()->latest('created_at')->first();
        $this->assertSame($result->correlationId, $record->correlation_id);
        $this->assertSame(AuthorizationDecision::Allowed, $record->decision);
    }

    public function test_a_denial_is_also_traced(): void
    {
        $user = $this->makeUser('trace-refus@example.com');

        $countBefore = AuthorizationDecisionRecord::query()->count();

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'nonexistent.capability'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame($countBefore + 1, AuthorizationDecisionRecord::query()->count());

        $record = AuthorizationDecisionRecord::query()->latest('created_at')->first();
        $this->assertSame(AuthorizationDecision::Denied, $record->decision);
        $this->assertSame('unknown_or_inactive_capability', $record->reason_code);
    }

    public function test_authorization_decisions_journal_refuses_update(): void
    {
        $user = $this->makeUser('journal-update@example.com');
        app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'nonexistent.capability'));
        $record = AuthorizationDecisionRecord::query()->latest('created_at')->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('governance.authorization_decisions')->where('id', $record->id)->update(['reason_code' => 'tampered']);
    }

    public function test_authorization_decisions_journal_refuses_delete(): void
    {
        $user = $this->makeUser('journal-delete@example.com');
        app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'nonexistent.capability'));
        $record = AuthorizationDecisionRecord::query()->latest('created_at')->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('governance.authorization_decisions')->where('id', $record->id)->delete();
    }

    public function test_grant_events_journal_refuses_update(): void
    {
        $user = $this->makeUser('evenements-update@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $event = GrantEvent::query()->latest('occurred_at')->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('governance.grant_events')->where('id', $event->id)->update(['reason' => 'tampered']);
    }

    public function test_grant_events_journal_refuses_delete(): void
    {
        $user = $this->makeUser('evenements-delete@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $event = GrantEvent::query()->latest('occurred_at')->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('governance.grant_events')->where('id', $event->id)->delete();
    }

    public function test_no_sensitive_data_appears_in_the_journals(): void
    {
        $user = $this->makeUser('donnees-sensibles@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $decisionColumns = Schema::getColumnListing('governance.authorization_decisions');
        $eventColumns = Schema::getColumnListing('governance.grant_events');

        $forbiddenFragments = ['password', 'secret', 'otp', 'kyc', 'medical', 'biometric', 'payload'];

        foreach (array_merge($decisionColumns, $eventColumns) as $column) {
            foreach ($forbiddenFragments as $fragment) {
                $this->assertStringNotContainsString($fragment, strtolower($column));
            }
        }
    }

    public function test_audit_failure_never_produces_an_allowed_decision(): void
    {
        $user = $this->makeUser('panne-audit@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        // Contrainte PostgreSQL temporaire, propre à ce test, forçant l'échec
        // de toute insertion dans le journal des décisions : simule une
        // panne d'audit sans ajouter aucun crochet de test au code de
        // production (P003-B1 §17).
        DB::statement('ALTER TABLE governance.authorization_decisions ADD CONSTRAINT authorization_decisions_test_forced_failure CHECK (false)');

        try {
            $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
                $user,
                'sample.read',
                resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
            ));

            $this->assertSame(AuthorizationDecision::Denied, $result->decision);
            $this->assertSame('audit_unavailable', $result->reason->code);
        } finally {
            DB::statement('ALTER TABLE governance.authorization_decisions DROP CONSTRAINT IF EXISTS authorization_decisions_test_forced_failure');
        }
    }
}
