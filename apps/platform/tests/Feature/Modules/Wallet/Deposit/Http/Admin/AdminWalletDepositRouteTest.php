<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Http\Admin;

use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

/**
 * Dépôts Wallet — supervision (TD-0008-D) : gouverné par
 * `wallet_deposit.review`, lecture seule (voir
 * `AdminWalletDepositController`).
 */
class AdminWalletDepositRouteTest extends TestCase
{
    use RefreshDatabase;
    use StaffCapabilityTesting;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/wallet-deposits');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/wallet-deposits');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposits')
            ->where('disputedDeposits.access.allowed', false)
            ->where('disputedDeposits.access.reason', 'no_active_grant')
            ->where('disputedDeposits.items', [])
            ->where('invalidWebhooks.access.allowed', false)
            ->where('invalidWebhooks.items', []),
        );
    }

    public function test_a_holder_of_wallet_deposit_review_sees_empty_queues_by_default(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.review', 'wallet.deposit');

        $response = $this->actingAs($staff->user)->get('/admin/wallet-deposits');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposits')
            ->where('disputedDeposits.access.allowed', true)
            ->where('disputedDeposits.items', [])
            ->where('invalidWebhooks.access.allowed', true)
            ->where('invalidWebhooks.items', []),
        );
    }

    public function test_a_holder_of_wallet_deposit_review_sees_a_disputed_deposit_but_not_a_completed_one(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.review', 'wallet.deposit');

        $depositor = $this->makeRepresentative();

        $disputed = Deposit::create([
            'person_id' => $depositor->person_id,
            'initiated_by_person_account_link_id' => $depositor->id,
            'state' => DepositState::UnknownReconciliation,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'ref-'.Str::uuid(),
            'idempotency_key' => 'idem-'.Str::uuid(),
        ]);

        Deposit::create([
            'person_id' => $depositor->person_id,
            'initiated_by_person_account_link_id' => $depositor->id,
            'state' => DepositState::Completed,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => 3000,
            'provider' => 'geniuspay',
            'provider_reference' => 'ref-'.Str::uuid(),
            'idempotency_key' => 'idem-'.Str::uuid(),
        ]);

        $response = $this->actingAs($staff->user)->get('/admin/wallet-deposits');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposits')
            ->has('disputedDeposits.items', 1)
            ->where('disputedDeposits.items.0.deposit_id', $disputed->id)
            ->where('disputedDeposits.items.0.amount', 5000),
        );
    }

    public function test_a_holder_of_wallet_deposit_review_sees_an_invalid_signature_webhook_but_not_a_valid_one(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'wallet_deposit.review', 'wallet.deposit');

        $invalid = DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'event_type' => 'payment.success',
            'signature_valid' => false,
            'raw_payload' => '{}',
            'received_at' => now(),
        ]);

        DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'event_type' => 'payment.success',
            'signature_valid' => true,
            'raw_payload' => '{}',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($staff->user)->get('/admin/wallet-deposits');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/wallet-deposits')
            ->has('invalidWebhooks.items', 1)
            ->where('invalidWebhooks.items.0.webhook_event_id', $invalid->id),
        );
    }

    public function test_the_admin_wallet_deposits_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.wallet-deposits'));
    }
}
