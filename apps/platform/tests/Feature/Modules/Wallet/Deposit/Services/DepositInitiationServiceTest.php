<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Services\DepositInitiationService;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

class DepositInitiationServiceTest extends WalletDepositTestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_deposit_with_a_checkout_url(): void
    {
        $link = $this->activeLinkFor($this->makeUser('depositor-'.Str::uuid().'@example.com'));

        $deposit = $this->app->make(DepositInitiationService::class)->initiate(
            depositId: (string) Str::uuid7(),
            personId: $link->person_id,
            initiatedByPersonAccountLinkId: $link->id,
            amount: 15000,
            successUrl: 'https://wasplex.test/wallet/deposits/return',
            errorUrl: 'https://wasplex.test/wallet/deposits/return',
            idempotencyKey: (string) Str::uuid(),
        );

        $this->assertSame(DepositState::Pending, $deposit->state);
        $this->assertSame(15000, $deposit->amount);
        $this->assertNotNull($deposit->checkout_url);
        $this->assertNotNull($deposit->provider_reference);
        $this->assertSame('CI', $deposit->country_code);
        $this->assertSame('XOF', $deposit->currency);
    }

    public function test_it_rejects_an_amount_below_the_provider_minimum(): void
    {
        $link = $this->activeLinkFor($this->makeUser('depositor-'.Str::uuid().'@example.com'));

        $this->expectException(\InvalidArgumentException::class);

        $this->app->make(DepositInitiationService::class)->initiate(
            depositId: (string) Str::uuid7(),
            personId: $link->person_id,
            initiatedByPersonAccountLinkId: $link->id,
            amount: 100,
            successUrl: 'https://wasplex.test/wallet/deposits/return',
            errorUrl: 'https://wasplex.test/wallet/deposits/return',
            idempotencyKey: (string) Str::uuid(),
        );
    }

    public function test_a_repeated_idempotency_key_returns_the_same_deposit_without_a_second_provider_call(): void
    {
        $link = $this->activeLinkFor($this->makeUser('depositor-'.Str::uuid().'@example.com'));
        $idempotencyKey = (string) Str::uuid();
        $service = $this->app->make(DepositInitiationService::class);

        $first = $service->initiate(
            depositId: (string) Str::uuid7(),
            personId: $link->person_id,
            initiatedByPersonAccountLinkId: $link->id,
            amount: 5000,
            successUrl: 'https://wasplex.test/wallet/deposits/return',
            errorUrl: 'https://wasplex.test/wallet/deposits/return',
            idempotencyKey: $idempotencyKey,
        );

        $second = $service->initiate(
            depositId: (string) Str::uuid7(),
            personId: $link->person_id,
            initiatedByPersonAccountLinkId: $link->id,
            amount: 5000,
            successUrl: 'https://wasplex.test/wallet/deposits/return',
            errorUrl: 'https://wasplex.test/wallet/deposits/return',
            idempotencyKey: $idempotencyKey,
        );

        $this->assertSame($first->id, $second->id);
        $this->assertCount(1, $this->geniusPay->requests);
    }

    public function test_a_provider_failure_leaves_the_deposit_in_draft(): void
    {
        $link = $this->activeLinkFor($this->makeUser('depositor-'.Str::uuid().'@example.com'));
        $this->geniusPay->shouldFail = true;

        try {
            $this->app->make(DepositInitiationService::class)->initiate(
                depositId: (string) Str::uuid7(),
                personId: $link->person_id,
                initiatedByPersonAccountLinkId: $link->id,
                amount: 5000,
                successUrl: 'https://wasplex.test/wallet/deposits/return',
                errorUrl: 'https://wasplex.test/wallet/deposits/return',
                idempotencyKey: (string) Str::uuid(),
            );
            $this->fail('expected GeniusPayRequestFailedException');
        } catch (GeniusPayRequestFailedException) {
            // attendu
        }

        $deposit = Deposit::query()->where('person_id', $link->person_id)->sole();
        $this->assertSame(DepositState::Draft, $deposit->state);
        $this->assertNull($deposit->checkout_url);
    }
}
