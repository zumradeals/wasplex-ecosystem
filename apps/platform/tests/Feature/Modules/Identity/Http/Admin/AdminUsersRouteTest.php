<?php

namespace Tests\Feature\Modules\Identity\Http\Admin;

use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

/**
 * Gestion admin des comptes utilisateurs (instruction explicite du
 * fondateur, 2026-07-31) : gouvernée par `identity.manage_users` —
 * mirroir de `AdminSectorClassificationRouteTest`.
 */
class AdminUsersRouteTest extends TestCase
{
    use RefreshDatabase;
    use StaffCapabilityTesting;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->where('access.allowed', false)
            ->where('users', []),
        );
    }

    public function test_a_holder_of_the_capability_can_create_a_new_user(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');

        $response = $this->actingAs($staff->user)->postJson('/admin/users', [
            'name' => 'Nouvel utilisateur',
            'email' => 'nouveau-'.Str::uuid().'@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJson(['account_state' => 'active', 'email_verified' => false]);
        $response->assertJsonStructure(['id', 'public_id', 'name', 'email']);

        $this->assertDatabaseHas('users', ['name' => 'Nouvel utilisateur']);
    }

    public function test_a_newly_created_user_receives_the_same_self_capabilities_as_a_real_registration(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');

        $response = $this->actingAs($staff->user)->postJson('/admin/users', [
            'name' => 'Compte support',
            'email' => 'support-'.Str::uuid().'@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated();

        $link = PersonAccountLink::query()
            ->where('user_id', $response->json('id'))
            ->where('status', 'active')
            ->firstOrFail();

        // Un compte créé par le personnel doit rester utilisable comme une
        // inscription en libre-service — même capacité self de base
        // (wallet.view fait partie du rôle user.base).
        $capability = CapabilityDefinition::query()->where('stable_key', 'wallet.view')->firstOrFail();
        $this->assertTrue(
            Grant::query()
                ->where('capability_definition_id', $capability->id)
                ->where('person_account_link_id', $link->id)
                ->where('state', GrantState::Active->value)
                ->exists(),
        );
    }

    public function test_the_created_account_is_attributed_to_support_review_not_self_registration(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');

        $response = $this->actingAs($staff->user)->postJson('/admin/users', [
            'name' => 'Compte support',
            'email' => 'support-'.Str::uuid().'@example.com',
            'password' => 'password123',
        ]);

        $link = PersonAccountLink::query()
            ->where('user_id', $response->json('id'))
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame(LinkOrigin::SupportReview, $link->origin);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');
        $existing = $this->makeUser('deja-'.Str::uuid().'@example.com');

        $response = $this->actingAs($staff->user)->postJson('/admin/users', [
            'name' => 'Doublon',
            'email' => $existing->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_subject_without_the_capability_cannot_create(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/users', [
            'name' => 'Intrus',
            'email' => 'intrus-'.Str::uuid().'@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_a_holder_of_the_capability_can_suspend_and_reactivate_a_user(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');
        $target = $this->makeUser('cible-'.Str::uuid().'@example.com');

        $suspend = $this->actingAs($staff->user)->postJson("/admin/users/{$target->id}/state", [
            'account_state' => 'suspended',
        ]);
        $suspend->assertOk();
        $suspend->assertJson(['account_state' => 'suspended']);

        $this->assertSame(
            'suspended',
            AssuranceState::query()->where('user_id', $target->id)->firstOrFail()->account_state->value,
        );

        $reactivate = $this->actingAs($staff->user)->postJson("/admin/users/{$target->id}/state", [
            'account_state' => 'active',
        ]);
        $reactivate->assertOk();
        $reactivate->assertJson(['account_state' => 'active']);
    }

    public function test_an_invalid_state_value_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');
        $target = $this->makeUser('cible-'.Str::uuid().'@example.com');

        $response = $this->actingAs($staff->user)->postJson("/admin/users/{$target->id}/state", [
            'account_state' => 'invited',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_subject_without_the_capability_cannot_change_state(): void
    {
        $user = $this->makeRepresentative();
        $target = $this->makeUser('cible-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user->user)->postJson("/admin/users/{$target->id}/state", [
            'account_state' => 'suspended',
        ]);

        $response->assertStatus(403);
    }

    public function test_search_filters_by_name_or_email(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'identity.manage_users', 'identity.user');
        $this->makeUser('findable-target-'.Str::uuid().'@example.com');
        $this->makeUser('other-'.Str::uuid().'@example.com');

        $response = $this->actingAs($staff->user)->get('/admin/users?q=findable-target');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->where('access.allowed', true)
            ->where('search', 'findable-target')
            ->has('users', 1),
        );
    }

    public function test_the_admin_users_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.users'));
        $this->assertTrue(Route::has('admin.users.store'));
        $this->assertTrue(Route::has('admin.users.state'));
    }
}
