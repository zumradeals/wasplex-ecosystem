<?php

namespace Tests\Feature\Modules\Advertising;

use App\Http\Controllers\GeniusPayWebhookController;
use App\Modules\Advertising\Enums\CampaignFundingState;
use App\Modules\Advertising\Models\CampaignFunding;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use App\Modules\Wallet\Deposit\Enums\DepositState;
use App\Modules\Wallet\Deposit\Models\Deposit;
use App\Modules\Wallet\Deposit\Models\DepositWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Financement de campagne : traitement du webhook GeniusPay partagé
 * (véto du dirigeant, 2026-07-30 ; `App\Http\Controllers\GeniusPayWebhookController`).
 * Mirroir de `Tests\Feature\Modules\Wallet\Deposit\Http\DepositWebhookRouteTest`,
 * plus une vérification de non-régression sur le chemin dépôt Wallet — même
 * URL, même contrôleur neutre.
 */
class CampaignFundingWebhookRouteTest extends CampaignFundingTestCase
{
    use RefreshDatabase;

    private function postWebhook(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/webhooks/geniuspay', [], [], [], [
            'HTTP_X_GENIUSPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_a_valid_webhook_credits_the_campaign_budget(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        $funding = CampaignFunding::create([
            'campaign_id' => $campaign->id,
            'initiated_by_person_account_link_id' => $representative->id,
            'state' => CampaignFundingState::Pending,
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-CFROUTE1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-CFROUTE1', 15000, fees: 450, netAmount: 14550);

        $response = $this->postWebhook($signed['body'], $signed['signature']);

        $response->assertStatus(200);

        $funding->refresh();
        $this->assertSame(CampaignFundingState::Completed, $funding->state);

        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 1);

        $projection = app(CampaignBudgetProjection::class);
        $this->assertSame(15000, $projection->available($campaign->fresh()));
    }

    public function test_an_amount_mismatch_moves_the_funding_to_unknown_reconciliation_never_a_success(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        $funding = CampaignFunding::create([
            'campaign_id' => $campaign->id,
            'initiated_by_person_account_link_id' => $representative->id,
            'state' => CampaignFundingState::Pending,
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-CFMISMATCH1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-CFMISMATCH1', 9999);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $funding->refresh();
        $this->assertSame(CampaignFundingState::UnknownReconciliation, $funding->state);
        $this->assertSame(0, app(CampaignBudgetProjection::class)->available($campaign->fresh()));
    }

    public function test_a_forged_signature_is_rejected_and_recorded_on_the_deposit_inbox(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-CFFORGED1', 15000);

        $response = $this->postWebhook($signed['body'], 'not-the-real-signature');

        $response->assertStatus(401);

        $event = DepositWebhookEvent::query()->sole();
        $this->assertFalse($event->signature_valid);
        $this->assertSame('signature_invalid', $event->processing_result);
        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 0);
    }

    public function test_replaying_the_same_webhook_never_double_credits(): void
    {
        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        CampaignFunding::create([
            'campaign_id' => $campaign->id,
            'initiated_by_person_account_link_id' => $representative->id,
            'state' => CampaignFundingState::Pending,
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-CFREPLAY1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-CFREPLAY1', 15000);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);
        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $projection = app(CampaignBudgetProjection::class);
        $this->assertSame(15000, $projection->available($campaign->fresh()));
    }

    /**
     * Non-régression : le contrôleur neutre partagé
     * ({@see GeniusPayWebhookController}) ne
     * modifie en rien le comportement déjà testé du dépôt Wallet — même
     * URL, chemin de traitement du dépôt inchangé.
     */
    public function test_a_deposit_referenced_webhook_still_credits_the_wallet_unchanged(): void
    {
        $link = $this->activeLinkFor($this->makeUser('payer-'.Str::uuid().'@example.com'));
        $deposit = Deposit::create([
            'person_id' => $link->person_id,
            'initiated_by_person_account_link_id' => $link->id,
            'state' => DepositState::Pending,
            'country_code' => 'CI',
            'currency' => 'XOF',
            'amount' => 15000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-DEPOSITROUTE1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-DEPOSITROUTE1', 15000, fees: 450, netAmount: 14550);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $deposit->refresh();
        $this->assertSame(DepositState::Completed, $deposit->state);

        // Aucune ligne de financement de campagne ne doit être créée par un
        // événement qui appartient au dépôt Wallet.
        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 0);

        $balances = $this->app->make(PersonBalanceProjection::class)->forPerson($link->person_id);
        $xof = collect($balances)->firstWhere('currency', 'XOF');
        $this->assertSame(15000, $xof['available']);
    }
}
