<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Advertising\Models\VideoAdDurationBounds;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Gestion admin des bornes de durée vidéo (Lot 4, véto du dirigeant
 * 2026-07-30) : gouvernée par `advertising.manage_video_duration_bounds` —
 * mirroir exact d'`AdminInterestTaxonomyRouteTest`.
 */
class AdminVideoDurationRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/video-duration-bounds');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/video-duration-bounds');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/video-duration-bounds')
            ->where('access.allowed', false)
            ->where('bounds', null),
        );
    }

    public function test_a_holder_of_the_capability_sees_the_active_bounds(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_video_duration_bounds', 'advertising.video_ad_duration_bounds');

        $response = $this->actingAs($staff->user)->get('/admin/video-duration-bounds');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/video-duration-bounds')
            ->where('access.allowed', true)
            ->where('bounds.min_seconds', 30)
            ->where('bounds.max_seconds', 60),
        );
    }

    public function test_a_holder_of_the_capability_can_publish_new_bounds_and_retires_the_previous_one(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_video_duration_bounds', 'advertising.video_ad_duration_bounds');
        $previous = VideoAdDurationBounds::query()->where('state', 'active')->firstOrFail();

        $response = $this->actingAs($staff->user)->postJson('/admin/video-duration-bounds', [
            'min_seconds' => 15,
            'max_seconds' => 90,
        ]);

        $response->assertCreated();
        $response->assertJson(['min_seconds' => 15, 'max_seconds' => 90, 'version' => 2]);
        $this->assertDatabaseHas('advertising.video_ad_duration_bounds', [
            'id' => $previous->id,
            'state' => 'retired',
        ]);
        $this->assertDatabaseHas('advertising.video_ad_duration_bounds', [
            'min_seconds' => 15,
            'max_seconds' => 90,
            'state' => 'active',
        ]);
    }

    public function test_a_subject_without_the_capability_cannot_publish_new_bounds(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/video-duration-bounds', [
            'min_seconds' => 15,
            'max_seconds' => 90,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('advertising.video_ad_duration_bounds', ['min_seconds' => 30, 'max_seconds' => 60, 'state' => 'active']);
    }

    public function test_the_admin_video_duration_bounds_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.video-duration-bounds'));
        $this->assertTrue(Route::has('admin.video-duration-bounds.store'));
    }
}
