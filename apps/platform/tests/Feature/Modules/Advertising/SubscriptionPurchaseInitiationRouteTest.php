<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Achat d'abonnement en libre-service (instruction explicite du fondateur,
 * 2026-07-31 ; `subscription.purchase`). Mirroir exact de
 * {@see AdvertiserWalletDepositInitiationRouteTest}.
 */
class SubscriptionPurchaseInitiationRouteTest extends CampaignFundingTestCase
{
    use RefreshDatabase;

    private function makeActivePlan(): SubscriptionPlan
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
            'stable_key' => 'premium',
            'name' => 'Premium',
            'version' => 1,
            'price_amount' => 5000,
            'currency' => 'XOF',
            'duration_days' => 30,
            'economic_type_id' => $economicType->id,
            'state' => ConfigurationState::Active,
        ]);
    }

    public function test_a_guest_receives_a_structured_401_not_a_redirect(): void
    {
        $plan = $this->makeActivePlan();

        $response = $this->postJson('/subscriptions/purchases', [
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['decision']);
    }

    public function test_a_subject_receives_a_checkout_url(): void
    {
        $representative = $this->makeRepresentative();
        $plan = $this->makeActivePlan();

        $response = $this->actingAs($representative->user)->postJson('/subscriptions/purchases', [
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $response->assertJson(['state' => 'pending']);
        $response->assertJsonStructure(['purchase_id', 'checkout_url']);
        $this->assertDatabaseHas('advertising.subscription_purchases', [
            'subscription_plan_id' => $plan->id,
            'amount' => 5000,
            'state' => 'pending',
        ]);
    }

    public function test_a_retired_plan_is_not_purchasable(): void
    {
        $representative = $this->makeRepresentative();
        $plan = $this->makeActivePlan();
        $plan->forceFill(['state' => ConfigurationState::Retired])->save();

        $response = $this->actingAs($representative->user)->postJson('/subscriptions/purchases', [
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404);
        $this->assertDatabaseCount('advertising.subscription_purchases', 0);
    }

    public function test_a_provider_outage_returns_a_service_unavailable_response(): void
    {
        $representative = $this->makeRepresentative();
        $plan = $this->makeActivePlan();
        $this->geniusPay->shouldFail = true;

        $response = $this->actingAs($representative->user)->postJson('/subscriptions/purchases', [
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(503);
        $response->assertJson(['reason' => 'payment_provider_unavailable']);
    }

    public function test_replaying_the_same_idempotency_key_returns_the_same_purchase(): void
    {
        $representative = $this->makeRepresentative();
        $plan = $this->makeActivePlan();
        $idempotencyKey = (string) Str::uuid();

        $first = $this->actingAs($representative->user)->postJson('/subscriptions/purchases', [
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => $idempotencyKey,
        ]);
        $second = $this->actingAs($representative->user)->postJson('/subscriptions/purchases', [
            'subscription_plan_id' => $plan->id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('purchase_id'), $second->json('purchase_id'));
        $this->assertSame(1, DB::table('advertising.subscription_purchases')->count());
    }

    public function test_the_subscription_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('subscriptions.index'));
        $this->assertTrue(Route::has('subscriptions.purchases.store'));
        $this->assertTrue(Route::has('subscriptions.purchases.return'));
    }
}
