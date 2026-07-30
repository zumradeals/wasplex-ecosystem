<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use App\Modules\Wallet\Deposit\Services\DepositWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

class DepositWebhookServiceTest extends WalletDepositTestCase
{
    use RefreshDatabase;

    private function makePendingDeposit(string $personId, int $amount = 15000, ?string $reference = null): Deposit
    {
        return Deposit::create([
            'person_id' => $personId,
            'initiated_by_person_account_link_id' => $this->activeLinkFor($this->makeUser('initiator-'.Str::uuid().'@example.com'))->id,
            'state' => DepositState::Pending,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => $amount,
            'provider' => 'geniuspay',
            'provider_payment_id' => (string) random_int(1000, 999999),
            'provider_reference' => $reference ?? 'MTX-'.strtoupper(Str::random(10)),
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function inboxEntry(array $signed, bool $signatureValid = true): DepositWebhookEvent
    {
        return DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'signature_valid' => $signatureValid,
            'raw_payload' => $signed['body'],
        ]);
    }

    public function test_a_successful_payment_credits_the_exact_requested_amount_net_of_fees(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));
        $deposit = $this->makePendingDeposit($link->person_id, 15000, 'MTX-SUCCESS1');

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-SUCCESS1', 15000, fees: 450, netAmount: 14550);
        $event = $this->inboxEntry($signed);

        $this->app->make(DepositWebhookService::class)->process($event);

        $deposit->refresh();
        $this->assertSame(DepositState::Completed, $deposit->state);
        $this->assertNotNull($deposit->ledger_transaction_id);

        $balances = $this->app->make(PersonBalanceProjection::class)->forPerson($link->person_id);
        $xof = collect($balances)->firstWhere('currency', 'XOF');
        $this->assertSame(15000, $xof['available']);

        $event->refresh();
        $this->assertNotNull($event->processed_at);
        $this->assertSame('credited', $event->processing_result);
    }

    public function test_a_failed_payment_marks_the_deposit_failed_without_crediting(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));
        $deposit = $this->makePendingDeposit($link->person_id, 8000, 'MTX-FAILED1');

        $signed = $this->signedWebhookPayload('payment.failed', 'MTX-FAILED1', 8000);
        $event = $this->inboxEntry($signed);

        $this->app->make(DepositWebhookService::class)->process($event);

        $deposit->refresh();
        $this->assertSame(DepositState::Failed, $deposit->state);
        $this->assertNull($deposit->ledger_transaction_id);

        $balances = $this->app->make(PersonBalanceProjection::class)->forPerson($link->person_id);
        $this->assertSame([], $balances);
    }

    public function test_an_amount_mismatch_goes_to_unknown_reconciliation_instead_of_completed(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));
        $deposit = $this->makePendingDeposit($link->person_id, 10000, 'MTX-MISMATCH1');

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-MISMATCH1', 9999);
        $event = $this->inboxEntry($signed);

        $this->app->make(DepositWebhookService::class)->process($event);

        $deposit->refresh();
        $this->assertSame(DepositState::UnknownReconciliation, $deposit->state);

        $balances = $this->app->make(PersonBalanceProjection::class)->forPerson($link->person_id);
        $this->assertSame([], $balances);
    }

    public function test_replaying_the_same_webhook_never_credits_twice(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));
        $deposit = $this->makePendingDeposit($link->person_id, 5000, 'MTX-REPLAY1');

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-REPLAY1', 5000, fees: 0, netAmount: 5000);
        $service = $this->app->make(DepositWebhookService::class);

        $firstEvent = $this->inboxEntry($signed);
        $service->process($firstEvent);

        $secondEvent = $this->inboxEntry($signed);
        $service->process($secondEvent);

        $secondEvent->refresh();
        $this->assertSame('deposit_already_terminal', $secondEvent->processing_result);

        $balances = $this->app->make(PersonBalanceProjection::class)->forPerson($link->person_id);
        $xof = collect($balances)->firstWhere('currency', 'XOF');
        $this->assertSame(5000, $xof['available']);
    }

    public function test_an_invalid_signature_is_recorded_but_never_processed(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-BADSIG', 5000);
        $event = $this->inboxEntry($signed, signatureValid: false);

        $this->app->make(DepositWebhookService::class)->process($event);

        $event->refresh();
        $this->assertSame('signature_invalid', $event->processing_result);
        $this->assertNotNull($event->processed_at);
    }

    public function test_an_unhandled_event_type_is_ignored_without_effect(): void
    {
        $signed = $this->signedWebhookPayload('payment.refunded', 'MTX-REFUND1', 5000);
        $event = $this->inboxEntry($signed);

        $this->app->make(DepositWebhookService::class)->process($event);

        $event->refresh();
        $this->assertSame('ignored_event_type', $event->processing_result);
    }

    public function test_a_webhook_for_an_unknown_reference_is_recorded_without_effect(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-UNKNOWN', 5000);
        $event = $this->inboxEntry($signed);

        $this->app->make(DepositWebhookService::class)->process($event);

        $event->refresh();
        $this->assertSame('deposit_not_found', $event->processing_result);
    }
}
