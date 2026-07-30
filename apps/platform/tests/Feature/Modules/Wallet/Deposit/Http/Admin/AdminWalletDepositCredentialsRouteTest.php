<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Http\Admin;

use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Wallet\Deposit\Models\ProviderCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

/**
 * Configuration admin des clés GeniusPay (véto du dirigeant 2026-07-30) :
 * gouvernée par `wallet_deposit.manage_credentials`, écriture critique
 * (voir `AdminWalletDepositCredentialsController`).
 */
class AdminWalletDepositCredentialsRouteTest extends TestCase
{
    use RefreshDatabase;
    use StaffCapabilityTesting;

    private function withStrongSession(): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/wallet-deposits/credentials');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/wallet-deposits/credentials');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposit-credentials')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_active_grant')
            ->where('credentials', null),
        );
    }

    public function test_a_weak_session_sees_a_step_up_denied_state(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.manage_credentials', 'wallet.deposit_provider_credentials');

        $response = $this->actingAs($staff->user)->get('/admin/wallet-deposits/credentials');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposit-credentials')
            ->where('access.allowed', false)
            ->where('access.reason', 'session_assurance_insufficient')
            ->where('credentials', null),
        );
    }

    public function test_a_holder_of_the_capability_sees_an_unconfigured_state_by_default(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.manage_credentials', 'wallet.deposit_provider_credentials');

        $response = $this->withStrongSession()->actingAs($staff->user)->get('/admin/wallet-deposits/credentials');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposit-credentials')
            ->where('access.allowed', true)
            ->where('credentials.base_url', null)
            ->where('credentials.api_key_configured', false)
            ->where('credentials.api_secret_configured', false)
            ->where('credentials.webhook_secret_configured', false),
        );
    }

    public function test_a_weak_session_receives_step_up_required_and_writes_nothing(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.manage_credentials', 'wallet.deposit_provider_credentials');

        $response = $this->actingAs($staff->user)->postJson('/admin/wallet-deposits/credentials', [
            'base_url' => 'https://pay.genius.ci/api/v1/merchant',
            'api_key' => 'pk_live_test',
            'api_secret' => 'sk_live_test',
            'webhook_secret' => 'whsec_test',
        ]);

        $response->assertStatus(403);
        $this->assertSame('session_assurance_insufficient', $response->json('reason'));
        $this->assertSame('strong', $response->json('required_session_assurance'));
        $this->assertDatabaseCount('ledger.wallet_deposit_provider_credentials', 0);
    }

    public function test_a_subject_without_the_capability_is_denied_even_with_a_strong_session(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->withStrongSession()->actingAs($user)->postJson('/admin/wallet-deposits/credentials', [
            'base_url' => 'https://pay.genius.ci/api/v1/merchant',
        ]);

        $response->assertStatus(403);
        $this->assertSame('no_active_grant', $response->json('reason'));
    }

    public function test_a_holder_with_a_strong_session_configures_the_credentials(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.manage_credentials', 'wallet.deposit_provider_credentials');

        $response = $this->withStrongSession()->actingAs($staff->user)->postJson('/admin/wallet-deposits/credentials', [
            'base_url' => 'https://pay.genius.ci/api/v1/merchant',
            'api_key' => 'pk_live_test',
            'api_secret' => 'sk_live_test',
            'webhook_secret' => 'whsec_test',
        ]);

        $response->assertOk();
        $response->assertJson([
            'base_url' => 'https://pay.genius.ci/api/v1/merchant',
            'api_key_configured' => true,
            'api_secret_configured' => true,
            'webhook_secret_configured' => true,
        ]);

        $stored = ProviderCredential::query()->where('provider', 'geniuspay')->firstOrFail();
        $this->assertSame('pk_live_test', $stored->api_key);
        $this->assertSame($staff->id, $stored->updated_by_person_account_link_id);

        // La colonne en base ne porte jamais la valeur en clair (cast
        // `encrypted`) : seule la lecture via le modèle la déchiffre.
        $rawValue = DB::table('ledger.wallet_deposit_provider_credentials')
            ->where('id', $stored->id)
            ->value('api_key');
        $this->assertNotSame('pk_live_test', $rawValue);
    }

    public function test_leaving_a_secret_field_blank_preserves_the_previously_stored_value(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.manage_credentials', 'wallet.deposit_provider_credentials');

        $this->withStrongSession()->actingAs($staff->user)->postJson('/admin/wallet-deposits/credentials', [
            'base_url' => 'https://pay.genius.ci/api/v1/merchant',
            'api_key' => 'pk_live_original',
            'api_secret' => 'sk_live_original',
            'webhook_secret' => 'whsec_original',
        ])->assertOk();

        $response = $this->withStrongSession()->actingAs($staff->user)->postJson('/admin/wallet-deposits/credentials', [
            'base_url' => 'https://pay.genius.ci/api/v1/merchant',
            'api_key' => '',
            'api_secret' => null,
            'webhook_secret' => null,
        ]);

        $response->assertOk();

        $stored = ProviderCredential::query()->where('provider', 'geniuspay')->firstOrFail();
        $this->assertSame('pk_live_original', $stored->api_key);
        $this->assertSame('sk_live_original', $stored->api_secret);
        $this->assertSame('whsec_original', $stored->webhook_secret);
        $this->assertSame(1, ProviderCredential::query()->count());
    }

    public function test_the_admin_wallet_deposit_credentials_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.wallet-deposit-credentials'));
        $this->assertTrue(Route::has('admin.wallet-deposit-credentials.update'));
    }
}
