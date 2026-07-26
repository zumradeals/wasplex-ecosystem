<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * « Mon espace » (W4, demande Koné 2026-07-26) — cinquième destination de
 * la navigation mobile. Ne modifie que name/email (users), déjà validés
 * par ProfileValidationRules ; voir AccountOverviewController pour le
 * raisonnement sur l'absence de nouveau champ de profil (AMD-0009) et sur
 * la réponse JSON plutôt qu'une redirection (contrairement à
 * Settings\ProfileController).
 */
class AccountOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/me');

        $response->assertRedirect('/login');
    }

    public function test_the_page_renders_the_authenticated_users_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Awa Koné', 'email' => 'awa@example.com']);

        $response = $this->actingAs($user)->get('/me');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('account/overview')
            ->where('auth.user.name', 'Awa Koné')
            ->where('auth.user.email', 'awa@example.com'),
        );
    }

    public function test_a_guest_is_redirected_to_login_when_updating(): void
    {
        // `account.profile.update` vit dans le même groupe 'auth' que la
        // page 'me' elle-même (action d'écran, pas un point de terminaison
        // API autonome) : redirection, pas de 401 JSON — même convention
        // que les autres pages de ce fichier (wallet, advertising, admin).
        $response = $this->post('/me/profile', [
            'name' => 'Nouveau nom',
            'email' => 'nouveau@example.com',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_an_authenticated_user_can_update_name_and_email(): void
    {
        $user = User::factory()->create(['name' => 'Ancien nom', 'email' => 'ancien@example.com']);

        $response = $this->actingAs($user)->postJson('/me/profile', [
            'name' => 'Nouveau nom',
            'email' => 'nouveau@example.com',
        ]);

        $response->assertOk();
        $response->assertJson([
            'name' => 'Nouveau nom',
            'email' => 'nouveau@example.com',
            'email_verified_at' => null,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nouveau nom',
            'email' => 'nouveau@example.com',
            'email_verified_at' => null,
        ]);
    }

    public function test_changing_the_email_resets_verification_but_keeping_it_does_not(): void
    {
        $user = User::factory()->create(['email' => 'stable@example.com', 'email_verified_at' => now()]);

        $response = $this->actingAs($user)->postJson('/me/profile', [
            'name' => $user->name,
            'email' => 'stable@example.com',
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('email_verified_at'));
    }

    public function test_an_invalid_update_returns_field_errors_and_does_not_persist(): void
    {
        $user = User::factory()->create(['name' => 'Nom original', 'email' => 'original@example.com']);

        $response = $this->actingAs($user)->postJson('/me/profile', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nom original',
            'email' => 'original@example.com',
        ]);
    }

    public function test_an_email_already_used_by_another_account_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $response = $this->actingAs($user)->postJson('/me/profile', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_the_account_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('account.overview'));
        $this->assertTrue(Route::has('account.profile.update'));
    }
}
