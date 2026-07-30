<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Http;

use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

class DepositReturnRouteTest extends WalletDepositTestCase
{
    use RefreshDatabase;

    private function makeDeposit(string $personId, DepositState $state = DepositState::Pending): Deposit
    {
        return Deposit::create([
            'person_id' => $personId,
            'initiated_by_person_account_link_id' => $this->activeLinkFor($this->makeUser('initiator-'.Str::uuid().'@example.com'))->id,
            'state' => $state,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-'.strtoupper(Str::random(10)),
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $owner = $this->activeLinkFor($this->makeUser('owner-'.Str::uuid().'@example.com'));
        $deposit = $this->makeDeposit($owner->person_id);

        $response = $this->get("/wallet/deposits/{$deposit->id}/return");

        $response->assertRedirect('/login');
    }

    public function test_the_owner_sees_the_honest_current_state_from_the_database(): void
    {
        $owner = $this->makeUser('owner-'.Str::uuid().'@example.com');
        $ownerLink = $this->activeLinkFor($owner);
        $deposit = $this->makeDeposit($ownerLink->person_id, DepositState::Completed);

        $response = $this->actingAs($owner)->get("/wallet/deposits/{$deposit->id}/return");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('wallet/deposit-return')
            ->where('access.allowed', true)
            ->where('deposit.state', 'completed'),
        );
    }

    public function test_a_stranger_cannot_view_someone_elses_deposit(): void
    {
        $owner = $this->activeLinkFor($this->makeUser('owner-'.Str::uuid().'@example.com'));
        $deposit = $this->makeDeposit($owner->person_id);

        $stranger = $this->makeUser('stranger-'.Str::uuid().'@example.com');

        $response = $this->actingAs($stranger)->get("/wallet/deposits/{$deposit->id}/return");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('wallet/deposit-return')
            ->where('access.allowed', false),
        );
    }
}
