<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Gestion admin de la référence des centres d'intérêt (véto du dirigeant,
 * 2026-07-30) : gouvernée par `advertising.manage_interest_taxonomy`.
 */
class AdminInterestTaxonomyRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/interest-taxonomy');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/interest-taxonomy');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/interest-taxonomy')
            ->where('access.allowed', false)
            ->where('entries', []),
        );
    }

    public function test_a_holder_of_the_capability_can_add_an_entry(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_interest_taxonomy', 'advertising.interest_taxonomy_entry');

        $response = $this->actingAs($staff->user)->postJson('/admin/interest-taxonomy', [
            'code' => 'sport',
            'label' => 'Sport',
        ]);

        $response->assertCreated();
        $response->assertJson(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);
        $this->assertDatabaseHas('advertising.interest_taxonomy_entries', ['code' => 'sport', 'state' => 'active']);
    }

    public function test_a_subject_without_the_capability_cannot_add_an_entry(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/interest-taxonomy', [
            'code' => 'sport',
            'label' => 'Sport',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('advertising.interest_taxonomy_entries', 0);
    }

    public function test_a_duplicate_code_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_interest_taxonomy', 'advertising.interest_taxonomy_entry');
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);

        $response = $this->actingAs($staff->user)->postJson('/admin/interest-taxonomy', [
            'code' => 'sport',
            'label' => 'Sport bis',
        ]);

        $response->assertStatus(422);
    }

    public function test_toggle_retires_and_reactivates_an_entry(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_interest_taxonomy', 'advertising.interest_taxonomy_entry');
        $entry = InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);

        $retire = $this->actingAs($staff->user)->postJson("/admin/interest-taxonomy/{$entry->id}/toggle", []);
        $retire->assertOk();
        $retire->assertJson(['state' => 'retired']);

        $reactivate = $this->actingAs($staff->user)->postJson("/admin/interest-taxonomy/{$entry->id}/toggle", []);
        $reactivate->assertOk();
        $reactivate->assertJson(['state' => 'active']);
    }

    public function test_the_admin_interest_taxonomy_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.interest-taxonomy'));
        $this->assertTrue(Route::has('admin.interest-taxonomy.store'));
        $this->assertTrue(Route::has('admin.interest-taxonomy.toggle'));
    }
}
