<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Gestion admin des trois types économiques (instruction explicite du
 * fondateur, 2026-07-31 ; docs/02 §3, §8) : gouvernée par
 * `advertising.manage_economic_types` — mirroir de
 * `AdminSectorClassificationRouteTest`.
 */
class AdminEconomicTypesRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'stable_key' => 'gold',
            'name' => 'Gold',
            'user_share_percentage' => 80,
            'monthly_quota' => 50,
            'is_default' => false,
        ], $overrides);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/economic-types');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/economic-types');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/economic-types')
            ->where('access.allowed', false)
            ->where('economicTypes', []),
        );
    }

    public function test_a_holder_of_the_capability_can_publish_a_new_type(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_economic_types', 'advertising.economic_type');

        $response = $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload());

        $response->assertCreated();
        $response->assertJson([
            'stable_key' => 'gold',
            'user_share_percentage' => 80,
            'monthly_quota' => 50,
            'is_default' => false,
            'version' => 1,
            'state' => 'active',
        ]);
        $this->assertDatabaseHas('advertising.economic_types', [
            'stable_key' => 'gold',
            'version' => 1,
            'state' => 'active',
        ]);
    }

    public function test_publishing_a_new_version_retires_only_the_same_stable_key(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_economic_types', 'advertising.economic_type');

        $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload(['stable_key' => 'silver']))
            ->assertCreated();

        $response = $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload([
            'stable_key' => 'gold',
            'user_share_percentage' => 90,
        ]));
        $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload([
            'stable_key' => 'gold',
            'user_share_percentage' => 95,
        ]))->assertCreated();

        $response->assertCreated();

        $this->assertDatabaseHas('advertising.economic_types', [
            'stable_key' => 'gold',
            'version' => 1,
            'state' => 'retired',
        ]);
        $this->assertDatabaseHas('advertising.economic_types', [
            'stable_key' => 'gold',
            'version' => 2,
            'state' => 'active',
        ]);
        $this->assertDatabaseHas('advertising.economic_types', [
            'stable_key' => 'silver',
            'version' => 1,
            'state' => 'active',
        ]);
    }

    public function test_publishing_a_new_default_clears_the_default_flag_from_others(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_economic_types', 'advertising.economic_type');

        $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload([
            'stable_key' => 'silver',
            'is_default' => true,
        ]))->assertCreated();

        $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload([
            'stable_key' => 'gold',
            'is_default' => true,
        ]))->assertCreated();

        $this->assertDatabaseHas('advertising.economic_types', [
            'stable_key' => 'silver',
            'is_default' => false,
        ]);
        $this->assertDatabaseHas('advertising.economic_types', [
            'stable_key' => 'gold',
            'is_default' => true,
        ]);
    }

    public function test_an_out_of_range_percentage_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_economic_types', 'advertising.economic_type');

        $response = $this->actingAs($staff->user)->postJson('/admin/economic-types', $this->validPayload([
            'user_share_percentage' => 150,
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('advertising.economic_types', ['stable_key' => 'gold']);
    }

    public function test_a_subject_without_the_capability_cannot_publish(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/economic-types', $this->validPayload());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('advertising.economic_types', ['stable_key' => 'gold']);
    }

    public function test_the_admin_economic_type_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.economic-types'));
        $this->assertTrue(Route::has('admin.economic-types.store'));
    }
}
