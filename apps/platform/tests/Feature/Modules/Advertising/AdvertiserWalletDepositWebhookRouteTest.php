<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\AdvertiserWalletDepositState;
use App\Modules\Advertising\Models\AdvertiserWalletDeposit;
use App\Modules\Advertising\Projections\AdvertiserWalletBalanceProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Dépôt Wallet annonceur : traitement du webhook GeniusPay partagé
 * (instruction explicite du fondateur, 2026-07-31 ;
 * `App\Http\Controllers\GeniusPayWebhookController`). Mirroir de
 * {@see CampaignFundingWebhookRouteTest} — troisième branche du même
 * contrôleur neutre, atteinte seulement quand ni le dépôt Wallet personnel
 * ni le financement de campagne ne reconnaissent la référence.
 */
class AdvertiserWalletDepositWebhookRouteTest extends CampaignFundingTestCase
{
    use RefreshDatabase;

    private function postWebhook(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/webhooks/geniuspay', [], [], [], [
            'HTTP_X_GENIUSPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_a_valid_webhook_credits_the_advertiser_wallet_balance(): void
    {
        $advertiser = $this->makeAdvertiserProfile();

        $deposit = AdvertiserWalletDeposit::create([
            'advertiser_profile_id' => $advertiser->id,
            'initiated_by_person_account_link_id' => $advertiser->representative->id,
            'state' => AdvertiserWalletDepositState::Pending,
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-AWROUTE1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-AWROUTE1', 15000, fees: 450, netAmount: 14550);

        $response = $this->postWebhook($signed['body'], $signed['signature']);

        $response->assertStatus(200);

        $deposit->refresh();
        $this->assertSame(AdvertiserWalletDepositState::Completed, $deposit->state);

        $this->assertDatabaseCount('advertising.advertiser_wallet_deposit_webhook_events', 1);
        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 1);

        $balance = collect(app(AdvertiserWalletBalanceProjection::class)->forAdvertiser($advertiser->fresh()))
            ->firstWhere('currency', 'XOF');
        $this->assertSame(15000, $balance['available']);
    }

    public function test_an_amount_mismatch_moves_the_deposit_to_unknown_reconciliation_never_a_success(): void
    {
        $advertiser = $this->makeAdvertiserProfile();

        $deposit = AdvertiserWalletDeposit::create([
            'advertiser_profile_id' => $advertiser->id,
            'initiated_by_person_account_link_id' => $advertiser->representative->id,
            'state' => AdvertiserWalletDepositState::Pending,
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-AWMISMATCH1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-AWMISMATCH1', 9999);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $deposit->refresh();
        $this->assertSame(AdvertiserWalletDepositState::UnknownReconciliation, $deposit->state);
        $this->assertSame([], app(AdvertiserWalletBalanceProjection::class)->forAdvertiser($advertiser->fresh()));
    }

    public function test_replaying_the_same_webhook_never_double_credits(): void
    {
        $advertiser = $this->makeAdvertiserProfile();

        AdvertiserWalletDeposit::create([
            'advertiser_profile_id' => $advertiser->id,
            'initiated_by_person_account_link_id' => $advertiser->representative->id,
            'state' => AdvertiserWalletDepositState::Pending,
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-AWREPLAY1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-AWREPLAY1', 15000);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);
        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $balance = collect(app(AdvertiserWalletBalanceProjection::class)->forAdvertiser($advertiser->fresh()))
            ->firstWhere('currency', 'XOF');
        $this->assertSame(15000, $balance['available']);
    }

    public function test_a_reference_matching_nothing_is_recorded_across_all_three_inboxes(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-UNKNOWNREF1', 15000);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $this->assertDatabaseCount('ledger.wallet_deposit_webhook_events', 1);
        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 1);
        $this->assertDatabaseCount('advertising.advertiser_wallet_deposit_webhook_events', 1);
        $this->assertDatabaseCount('advertising.advertiser_wallet_deposits', 0);
    }
}
