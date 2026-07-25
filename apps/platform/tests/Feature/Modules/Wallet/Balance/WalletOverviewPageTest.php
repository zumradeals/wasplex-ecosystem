<?php

namespace Tests\Feature\Modules\Wallet\Balance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Premier écran de production réel du Wallet (P006-A2,
 * `U-006-01-apercu-wallet`). Contrairement à `WalletBalanceRouteTest` (API
 * JSON), cette route vit dans le groupe `auth`/`verified` : un visiteur non
 * authentifié est redirigé vers la connexion, jamais un 401 JSON.
 */
class WalletOverviewPageTest extends WalletBalanceTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/wallet');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_a_grant_sees_the_denied_state_not_a_crash(): void
    {
        $user = $this->makeUser('no-grant-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/wallet');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('wallet/overview')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_active_grant')
            ->where('balances', []),
        );
    }

    public function test_a_subject_with_an_active_grant_sees_their_own_balance(): void
    {
        $user = $this->makeUser('own-balance-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $this->grantWalletView($link);
        $this->creditAvailable($link->person_id, 2_000, 'XOF');

        $response = $this->actingAs($user)->get('/wallet');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('wallet/overview')
            ->where('access.allowed', true)
            ->where('balances', [
                ['currency' => 'XOF', 'available' => 2_000, 'provisional' => 0, 'reserved' => 0],
            ]),
        );
    }

    public function test_the_wallet_page_route_is_registered(): void
    {
        $webRoutes = file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("'wallet'", $webRoutes);
        $this->assertTrue(Route::has('wallet.show'));
    }
}
