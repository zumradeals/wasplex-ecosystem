<?php

namespace Tests\Feature\Modules\Governance\Authorization\Admin;

use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

/**
 * Accès (UX-0001 §8) : gouverné par `access.view`, lecture seule (voir
 * `AdminAccessController`).
 */
class AdminAccessRouteTest extends TestCase
{
    use RefreshDatabase;
    use StaffCapabilityTesting;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/access');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/access');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/access')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_active_grant')
            ->where('grants', []),
        );
    }

    public function test_a_holder_of_access_view_sees_the_grant_directory_including_its_own_grant(): void
    {
        $staff = $this->makeRepresentative();
        $grant = $this->grantStaffCapability($staff, 'access.view', 'governance.grant');

        $response = $this->actingAs($staff->user)->get('/admin/access');

        $response->assertOk();

        $props = null;
        $response->assertInertia(function (Assert $page) use (&$props) {
            $page->component('admin/access')->where('access.allowed', true);
            $props = $page->toArray();
        });

        // La liste inclut aussi les grants `user.base` auto-émis pour
        // chaque compte créé par la fabrique de test (staff, auteur,
        // approbateur du grant lui-même) : le registre est volontairement
        // système entier (aucune portée `self`, voir la migration de
        // déclaration), on vérifie donc que le grant attendu s'y trouve,
        // pas qu'il soit seul.
        $row = collect($props['props']['grants'])->firstWhere('grant_id', $grant->id);

        $this->assertNotNull($row, 'le grant access.view attendu est absent du registre');
        $this->assertSame('access.view', $row['capability_key']);
        $this->assertSame('active', $row['state']);
        $this->assertSame($staff->user->name, $row['holder_name']);
        $this->assertSame('governance.grant', $row['scope_type']);
    }

    public function test_the_admin_access_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.access'));
    }
}
