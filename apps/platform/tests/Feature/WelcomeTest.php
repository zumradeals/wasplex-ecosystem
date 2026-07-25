<?php

namespace Tests\Feature;

use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
    }

    public function test_home_page_renders_the_welcome_component_with_essential_structure(): void
    {
        $response = $this->get(route('home'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user', null)
        );

        $response->assertSee('<html lang="fr"', false);
        $response->assertSee('<title>Wasplex</title>', false);
        $response->assertSee('name="viewport"', false);
    }

    /**
     * Accueil public minimal (L01) : jamais de second passage par
     * l'explication pour un compte déjà authentifié — redirection directe
     * vers son écran d'accueil, même principe que le groupe
     * 'auth'/'verified'.
     */
    public function test_an_authenticated_visitor_is_redirected_straight_to_the_dashboard(): void
    {
        $user = app(RegistersUserIdentity::class)->register([
            'name' => 'Utilisateur Accueil',
            'email' => 'home-redirect@example.com',
            'password' => 'password',
        ]);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertRedirect(route('dashboard'));
    }
}
