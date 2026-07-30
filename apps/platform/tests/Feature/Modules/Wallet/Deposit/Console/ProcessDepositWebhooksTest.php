<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Console;

use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Wallet\Deposit\WalletDepositTestCase;

/**
 * Sûreté de la commande de reprise (ADR-0007 §11) : mêmes garanties
 * d'idempotence que le traitement en ligne du webhook — voir
 * `DepositWebhookServiceTest` pour le détail métier, ce test vérifie
 * seulement que la commande retraite bien les entrées non traitées.
 */
class ProcessDepositWebhooksTest extends WalletDepositTestCase
{
    use RefreshDatabase;

    public function test_it_processes_pending_inbox_entries_and_leaves_processed_ones_alone(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));

        $deposit = Deposit::create([
            'person_id' => $link->person_id,
            'initiated_by_person_account_link_id' => $link->id,
            'state' => DepositState::Pending,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-CMD1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-CMD1', 5000, fees: 0, netAmount: 5000);
        $pendingEvent = DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'signature_valid' => true,
            'raw_payload' => $signed['body'],
        ]);

        $alreadyProcessed = DepositWebhookEvent::create([
            'provider' => 'geniuspay',
            'signature_valid' => true,
            'raw_payload' => '{"event":"payment.success"}',
            'processed_at' => now(),
            'processing_result' => 'credited',
        ]);

        $this->artisan('wallet:process-deposit-webhooks')->assertSuccessful();

        $pendingEvent->refresh();
        $this->assertNotNull($pendingEvent->processed_at);
        $this->assertSame('credited', $pendingEvent->processing_result);

        $deposit->refresh();
        $this->assertSame(DepositState::Completed, $deposit->state);
    }
}
