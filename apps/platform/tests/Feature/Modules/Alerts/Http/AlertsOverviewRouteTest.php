<?php

namespace Tests\Feature\Modules\Alerts\Http;

use App\Modules\Alerts\Enums\CommunityCaseState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class AlertsOverviewRouteTest extends AlertsTestCase
{
    use RefreshDatabase;

    /**
     * AMD-0007 §2 ; Constitution article 14.2 : un SOS peut être créé sans
     * authentification complète — l'écran qui porte son formulaire doit
     * donc rester atteignable sans connexion, jamais rediriger un visiteur
     * anonyme vers `/login` avant qu'il n'atteigne le bouton SOS (control
     * de navigateur réel, dossier final P008-A).
     */
    public function test_a_guest_can_reach_the_screen_without_being_redirected_to_login(): void
    {
        $response = $this->get('/alerts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('alerts/index')
            ->where('my_declarations', []),
        );
    }

    public function test_an_authenticated_user_sees_their_own_declarations(): void
    {
        $user = $this->makeUser('declarant-'.Str::uuid().'@example.com');
        $link = $this->activeLinkFor($user);
        $this->makeCommunityCase($link, state: CommunityCaseState::Submitted);

        $response = $this->actingAs($user)->get('/alerts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('alerts/index')
            ->has('my_declarations', 1)
            ->where('my_declarations.0.state', 'submitted'),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('alerts.index'));
    }
}
