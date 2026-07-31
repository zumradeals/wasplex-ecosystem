<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Gestion admin des plans d'abonnement (instruction explicite du
 * fondateur, 2026-07-31 ; docs/02 §2, §8) : gouvernée par
 * `advertising.manage_subscription_plans` — mirroir de
 * `AdminEconomicTypesRouteTest`.
 */
class AdminSubscriptionPlansRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function makeActiveEconomicType(?string $stableKey = null): EconomicType
    {
        return EconomicType::create([
            'stable_key' => $stableKey ?? 'test-'.uniqid(),
            'name' => 'Type de test',
            'version' => 1,
            'user_share_percentage' => 100,
            'monthly_quota' => null,
            'is_default' => false,
            'state' => ConfigurationState::Active,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'stable_key' => 'premium',
            'name' => 'Premium',
            'price_amount' => 5000,
            'currency' => 'xof',
            'duration_days' => 30,
            'economic_type_id' => $this->makeActiveEconomicType()->id,
        ], $overrides);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/subscription-plans');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/subscription-plans');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/subscription-plans')
            ->where('access.allowed', false)
            ->where('plans', []),
        );
    }

    public function test_a_holder_of_the_capability_can_publish_a_new_plan(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_subscription_plans', 'advertising.subscription_plan');

        $response = $this->actingAs($staff->user)->postJson('/admin/subscription-plans', $this->validPayload());

        $response->assertCreated();
        $response->assertJson([
            'stable_key' => 'premium',
            'price_amount' => 5000,
            'currency' => 'XOF',
            'duration_days' => 30,
            'version' => 1,
            'state' => 'active',
        ]);
        $this->assertDatabaseHas('advertising.subscription_plans', [
            'stable_key' => 'premium',
            'version' => 1,
            'state' => 'active',
        ]);
    }

    public function test_publishing_a_new_version_retires_only_the_same_stable_key(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_subscription_plans', 'advertising.subscription_plan');
        $economicType = $this->makeActiveEconomicType();

        $this->actingAs($staff->user)->postJson('/admin/subscription-plans', $this->validPayload([
            'stable_key' => 'basic',
            'economic_type_id' => $economicType->id,
        ]))->assertCreated();

        $this->actingAs($staff->user)->postJson('/admin/subscription-plans', $this->validPayload([
            'stable_key' => 'premium',
            'economic_type_id' => $economicType->id,
        ]))->assertCreated();

        $this->actingAs($staff->user)->postJson('/admin/subscription-plans', $this->validPayload([
            'stable_key' => 'premium',
            'price_amount' => 6000,
            'economic_type_id' => $economicType->id,
        ]))->assertCreated();

        $this->assertDatabaseHas('advertising.subscription_plans', [
            'stable_key' => 'premium',
            'version' => 1,
            'state' => 'retired',
        ]);
        $this->assertDatabaseHas('advertising.subscription_plans', [
            'stable_key' => 'premium',
            'version' => 2,
            'state' => 'active',
        ]);
        $this->assertDatabaseHas('advertising.subscription_plans', [
            'stable_key' => 'basic',
            'version' => 1,
            'state' => 'active',
        ]);
    }

    public function test_a_price_of_zero_is_rejected_no_free_plans_in_this_lot(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_subscription_plans', 'advertising.subscription_plan');

        $response = $this->actingAs($staff->user)->postJson('/admin/subscription-plans', $this->validPayload([
            'price_amount' => 0,
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('advertising.subscription_plans', ['stable_key' => 'premium']);
    }

    public function test_a_missing_economic_type_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_subscription_plans', 'advertising.subscription_plan');

        $response = $this->actingAs($staff->user)->postJson('/admin/subscription-plans', $this->validPayload([
            'economic_type_id' => (string) Str::uuid(),
        ]));

        $response->assertStatus(422);
    }

    public function test_a_subject_without_the_capability_cannot_publish(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/subscription-plans', $this->validPayload());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('advertising.subscription_plans', ['stable_key' => 'premium']);
    }

    public function test_the_admin_subscription_plan_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.subscription-plans'));
        $this->assertTrue(Route::has('admin.subscription-plans.store'));
    }
}
