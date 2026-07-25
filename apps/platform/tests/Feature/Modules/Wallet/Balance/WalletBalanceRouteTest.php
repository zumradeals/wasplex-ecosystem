<?php

namespace Tests\Feature\Modules\Wallet\Balance;

use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Première route sensible réelle du module Wallet (P006-A) : consultation
 * du solde WP disponible d'une personne — jamais celui d'autrui. Suit
 * exactement le protocole déjà établi par `CampaignSubmissionRouteTest`
 * (P005-B) : 401 non authentifié, 403 sûr sans grant, 200 avec grant actif.
 */
class WalletBalanceRouteTest extends WalletBalanceTestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_subject_receives_401(): void
    {
        $response = $this->getJson('/wallet/balance');

        $response->assertStatus(401);
    }

    public function test_an_authenticated_subject_without_a_grant_receives_a_safe_403(): void
    {
        // Depuis P007, une inscription réelle émet automatiquement les
        // grants `user.base` (dont `wallet.view`) — `LinkOrigin::Migration`
        // est le seul moyen de garder ce test représentatif d'un sujet
        // réellement sans aucun grant.
        $user = $this->makeUser('no-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);

        $response = $this->actingAs($user)->getJson('/wallet/balance');

        $response->assertStatus(403);
        $response->assertJsonStructure(['decision', 'reason', 'correlation_id']);
        $this->assertSame('no_active_grant', $response->json('reason'));

        foreach (['grant_id', 'policy_stable_key', 'policy_version', 'stable_key', 'capability_definition'] as $forbiddenFragment) {
            $this->assertStringNotContainsStringIgnoringCase($forbiddenFragment, $response->getContent());
        }
    }

    public function test_a_subject_with_an_active_grant_and_no_credited_account_sees_an_empty_wallet(): void
    {
        $user = $this->makeUser('empty-wallet-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $this->grantWalletView($link);

        $response = $this->actingAs($user)->getJson('/wallet/balance');

        $response->assertStatus(200);
        $response->assertExactJson(['balances' => []]);
    }

    public function test_a_subject_with_an_active_grant_sees_their_own_available_balance(): void
    {
        $user = $this->makeUser('own-balance-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $this->grantWalletView($link);

        $this->creditAvailable($link->person_id, 1_500, 'XOF');
        $this->creditAvailable($link->person_id, 500, 'XOF');

        $response = $this->actingAs($user)->getJson('/wallet/balance');

        $response->assertStatus(200);
        $response->assertExactJson([
            'balances' => [
                ['currency' => 'XOF', 'available' => 2_000, 'provisional' => 0, 'reserved' => 0],
            ],
        ]);
    }

    public function test_a_subject_never_sees_another_persons_balance(): void
    {
        $owner = $this->makeUser('owner-'.Str::uuid().'@example.com');
        $ownerLink = $this->activeLinkFor($owner);
        $this->creditAvailable($ownerLink->person_id, 10_000, 'XOF');

        $viewer = $this->makeUser('viewer-'.Str::uuid().'@example.com');
        $viewerLink = $this->activeLinkFor($viewer);
        $this->grantWalletView($viewerLink);

        $response = $this->actingAs($viewer)->getJson('/wallet/balance');

        $response->assertStatus(200);
        $response->assertExactJson(['balances' => []]);
    }

    public function test_the_wallet_balance_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString('wallet/balance', $webRoutes);
        $this->assertTrue(Route::has('wallet.balance.show'));
    }

    public function test_the_wallet_view_capability_is_immutable_once_active(): void
    {
        $capability = CapabilityDefinition::query()->where('stable_key', 'wallet.view')->where('state', 'active')->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('governance.capability_definitions')
            ->where('id', $capability->id)
            ->update(['risk_class' => 'critical']);
    }

    public function test_no_ledger_postings_or_transactions_are_produced_by_this_route(): void
    {
        $user = $this->makeUser('ledger-check-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $this->grantWalletView($link);
        $this->creditAvailable($link->person_id, 1_000, 'XOF');

        $transactionsBefore = DB::table('ledger.ledger_transactions')->count();
        $postingsBefore = DB::table('ledger.postings')->count();

        $response = $this->actingAs($user)->getJson('/wallet/balance');

        $response->assertStatus(200);
        $this->assertSame($transactionsBefore, DB::table('ledger.ledger_transactions')->count());
        $this->assertSame($postingsBefore, DB::table('ledger.postings')->count());
    }
}
