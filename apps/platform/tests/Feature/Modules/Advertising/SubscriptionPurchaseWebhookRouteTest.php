<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Enums\SubscriptionPurchaseState;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\PersonSubscription;
use App\Modules\Advertising\Models\SubscriptionPlan;
use App\Modules\Advertising\Models\SubscriptionPurchase;
use App\Modules\Advertising\Services\EconomicTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Achat d'abonnement : traitement du webhook GeniusPay partagé (instruction
 * explicite du fondateur, 2026-07-31 ;
 * `App\Http\Controllers\GeniusPayWebhookController`). Mirroir de
 * {@see AdvertiserWalletDepositWebhookRouteTest} — quatrième branche du
 * même contrôleur neutre, atteinte seulement quand aucun des trois autres
 * domaines ne reconnaît la référence.
 */
class SubscriptionPurchaseWebhookRouteTest extends CampaignFundingTestCase
{
    use RefreshDatabase;

    private function postWebhook(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/webhooks/geniuspay', [], [], [], [
            'HTTP_X_GENIUSPAY_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function makeActivePlan(int $priceAmount = 5000): SubscriptionPlan
    {
        $economicType = EconomicType::create([
            'stable_key' => 'test-'.uniqid(),
            'name' => 'Type de test',
            'version' => 1,
            'user_share_percentage' => 100,
            'monthly_quota' => null,
            'is_default' => false,
            'state' => ConfigurationState::Active,
        ]);

        return SubscriptionPlan::create([
            'stable_key' => 'premium-'.uniqid(),
            'name' => 'Premium',
            'version' => 1,
            'price_amount' => $priceAmount,
            'currency' => 'XOF',
            'duration_days' => 30,
            'economic_type_id' => $economicType->id,
            'state' => ConfigurationState::Active,
        ]);
    }

    public function test_a_valid_webhook_activates_the_subscription_and_links_the_economic_type(): void
    {
        $beneficiary = $this->makeBeneficiary();
        $plan = $this->makeActivePlan();

        $purchase = SubscriptionPurchase::create([
            'person_id' => $beneficiary->person_id,
            'subscription_plan_id' => $plan->id,
            'initiated_by_person_account_link_id' => $beneficiary->id,
            'state' => SubscriptionPurchaseState::Pending,
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-SUBROUTE1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-SUBROUTE1', 5000, fees: 150, netAmount: 4850);

        $response = $this->postWebhook($signed['body'], $signed['signature']);

        $response->assertStatus(200);

        $purchase->refresh();
        $this->assertSame(SubscriptionPurchaseState::Completed, $purchase->state);
        $this->assertNotNull($purchase->ledger_transaction_id);

        $this->assertDatabaseCount('advertising.subscription_purchase_webhook_events', 1);
        $this->assertDatabaseCount('advertising.advertiser_wallet_deposit_webhook_events', 1);
        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 1);

        $subscription = PersonSubscription::query()->where('person_id', $beneficiary->person_id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame($plan->id, $subscription->subscription_plan_id);
        $this->assertTrue($subscription->isActive());

        $resolved = app(EconomicTypeResolver::class)->forPerson($beneficiary->person_id);
        $this->assertSame($plan->economic_type_id, $resolved->id);
    }

    public function test_an_amount_mismatch_moves_the_purchase_to_unknown_reconciliation_never_activating(): void
    {
        $beneficiary = $this->makeBeneficiary();
        $plan = $this->makeActivePlan();

        $purchase = SubscriptionPurchase::create([
            'person_id' => $beneficiary->person_id,
            'subscription_plan_id' => $plan->id,
            'initiated_by_person_account_link_id' => $beneficiary->id,
            'state' => SubscriptionPurchaseState::Pending,
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-SUBMISMATCH1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-SUBMISMATCH1', 9999);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $purchase->refresh();
        $this->assertSame(SubscriptionPurchaseState::UnknownReconciliation, $purchase->state);
        $this->assertDatabaseCount('advertising.person_subscriptions', 0);
    }

    public function test_replaying_the_same_webhook_never_extends_the_subscription_twice(): void
    {
        $beneficiary = $this->makeBeneficiary();
        $plan = $this->makeActivePlan();

        SubscriptionPurchase::create([
            'person_id' => $beneficiary->person_id,
            'subscription_plan_id' => $plan->id,
            'initiated_by_person_account_link_id' => $beneficiary->id,
            'state' => SubscriptionPurchaseState::Pending,
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-SUBREPLAY1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-SUBREPLAY1', 5000);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);
        $endsAtAfterFirst = PersonSubscription::query()->where('person_id', $beneficiary->person_id)->firstOrFail()->ends_at;

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);
        $endsAtAfterReplay = PersonSubscription::query()->where('person_id', $beneficiary->person_id)->firstOrFail()->ends_at;

        $this->assertTrue($endsAtAfterFirst->equalTo($endsAtAfterReplay));
        $this->assertSame(1, PersonSubscription::query()->where('person_id', $beneficiary->person_id)->count());
    }

    public function test_a_new_purchase_while_still_active_extends_from_the_current_expiry(): void
    {
        $beneficiary = $this->makeBeneficiary();
        $plan = $this->makeActivePlan();

        PersonSubscription::create([
            'person_id' => $beneficiary->person_id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->addDays(10),
        ]);

        $purchase = SubscriptionPurchase::create([
            'person_id' => $beneficiary->person_id,
            'subscription_plan_id' => $plan->id,
            'initiated_by_person_account_link_id' => $beneficiary->id,
            'state' => SubscriptionPurchaseState::Pending,
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'MTX-SUBEXTEND1',
            'checkout_url' => 'https://pay.genius.ci/checkout/x',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $signed = $this->signedWebhookPayload('payment.success', 'MTX-SUBEXTEND1', 5000);
        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $subscription = PersonSubscription::query()->where('person_id', $beneficiary->person_id)->firstOrFail();
        // Prolonge depuis la fin actuelle (+10 jours restants +30 jours du plan), jamais à partir d'aujourd'hui.
        $this->assertTrue($subscription->ends_at->isAfter(now()->addDays(39)));
    }

    public function test_a_reference_matching_nothing_is_recorded_across_all_four_inboxes(): void
    {
        $signed = $this->signedWebhookPayload('payment.success', 'MTX-UNKNOWNREF2', 15000);

        $this->postWebhook($signed['body'], $signed['signature'])->assertStatus(200);

        $this->assertDatabaseCount('ledger.wallet_deposit_webhook_events', 1);
        $this->assertDatabaseCount('advertising.campaign_funding_webhook_events', 1);
        $this->assertDatabaseCount('advertising.advertiser_wallet_deposit_webhook_events', 1);
        $this->assertDatabaseCount('advertising.subscription_purchase_webhook_events', 1);
        $this->assertDatabaseCount('advertising.subscription_purchases', 0);
    }
}
