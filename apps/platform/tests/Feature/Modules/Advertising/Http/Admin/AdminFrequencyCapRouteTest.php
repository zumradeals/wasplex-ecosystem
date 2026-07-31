<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Advertising\Models\FrequencyCapBounds;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Gestion admin du plafond de revisionnage gratuit (instruction explicite
 * du fondateur, 2026-07-31) : gouvernée par
 * `advertising.manage_frequency_cap` — mirroir exact
 * d'`AdminVideoDurationRouteTest`.
 */
class AdminFrequencyCapRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/frequency-cap');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/frequency-cap');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/frequency-cap')
            ->where('access.allowed', false)
            ->where('bounds', null),
        );
    }

    public function test_a_holder_of_the_capability_sees_the_active_bounds(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_frequency_cap', 'advertising.frequency_cap_bounds');

        $response = $this->actingAs($staff->user)->get('/admin/frequency-cap');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/frequency-cap')
            ->where('access.allowed', true)
            ->where('bounds.daily_free_view_limit', 3)
            ->where('bounds.lifetime_free_view_limit', 10),
        );
    }

    public function test_a_holder_of_the_capability_can_publish_new_bounds_and_retires_the_previous_one(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_frequency_cap', 'advertising.frequency_cap_bounds');
        $previous = FrequencyCapBounds::query()->where('state', 'active')->firstOrFail();

        $response = $this->actingAs($staff->user)->postJson('/admin/frequency-cap', [
            'daily_free_view_limit' => 5,
            'lifetime_free_view_limit' => 20,
        ]);

        $response->assertCreated();
        $response->assertJson(['daily_free_view_limit' => 5, 'lifetime_free_view_limit' => 20, 'version' => 2]);
        $this->assertDatabaseHas('advertising.frequency_cap_bounds', [
            'id' => $previous->id,
            'state' => 'retired',
        ]);
        $this->assertDatabaseHas('advertising.frequency_cap_bounds', [
            'daily_free_view_limit' => 5,
            'lifetime_free_view_limit' => 20,
            'state' => 'active',
        ]);
    }

    public function test_a_lifetime_limit_below_the_daily_limit_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_frequency_cap', 'advertising.frequency_cap_bounds');

        $response = $this->actingAs($staff->user)->postJson('/admin/frequency-cap', [
            'daily_free_view_limit' => 5,
            'lifetime_free_view_limit' => 2,
        ]);

        $response->assertStatus(422);
    }

    public function test_a_subject_without_the_capability_cannot_publish_new_bounds(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/frequency-cap', [
            'daily_free_view_limit' => 5,
            'lifetime_free_view_limit' => 20,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('advertising.frequency_cap_bounds', ['daily_free_view_limit' => 3, 'lifetime_free_view_limit' => 10, 'state' => 'active']);
    }

    public function test_the_admin_frequency_cap_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.frequency-cap'));
        $this->assertTrue(Route::has('admin.frequency-cap.store'));
    }
}
