<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Integration\SessionAssuranceResolver;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Force de session réellement prouvée (P003-B2 §B). Aucun palier
 * intermédiaire n'est deviné : `weak` par défaut, `strong` uniquement si
 * Laravel a lui-même constaté une reconfirmation récente du mot de passe,
 * dans la fenêtre déjà configurée par `auth.password_timeout`.
 */
class SessionAssuranceResolverTest extends TestCase
{
    public function test_a_plain_authenticated_session_is_weak_by_default(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);

        $assurance = (new SessionAssuranceResolver)->fromRequest($request);

        $this->assertSame(SessionAssurance::Weak, $assurance);
    }

    public function test_a_recent_password_confirmation_is_strong(): void
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('auth.password_confirmed_at', time());

        $assurance = (new SessionAssuranceResolver)->fromRequest($request);

        $this->assertSame(SessionAssurance::Strong, $assurance);
    }

    public function test_an_expired_password_confirmation_falls_back_to_weak(): void
    {
        $timeout = (int) config('auth.password_timeout', 10800);

        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('auth.password_confirmed_at', time() - $timeout - 60);

        $assurance = (new SessionAssuranceResolver)->fromRequest($request);

        $this->assertSame(SessionAssurance::Weak, $assurance);
    }
}
