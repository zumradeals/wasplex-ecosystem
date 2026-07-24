<?php

namespace Tests\Feature\Modules\Governance\Authorization\Integration;

use App\Models\User;
use App\Modules\Governance\Authorization\Integration\SessionAssuranceResolver;
use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * TD-0002-A : preuve empirique, exécutée par un véritable aller-retour HTTP
 * à travers le pipeline de login Fortify réel (pas une supposition sur son
 * comportement), que rien ne distingue aujourd'hui, côté session, un login
 * ayant réellement traversé un défi 2FA vérifié d'un login par mot de passe
 * seul. Si une future version de `laravel/fortify` venait à changer ce
 * comportement (par exemple en persistant un marqueur de session après
 * succès), ce test cesserait de passer — signal exact qu'il faudrait alors
 * rouvrir TD-0002-A plutôt que la supposition inverse.
 *
 * Le login par passkey n'est pas rejoué ici (la cérémonie WebAuthn réelle
 * exigerait de simuler une paire de clés et un authenticator complets) :
 * son absence de marqueur de session a été vérifiée par lecture directe de
 * `Laravel\Passkeys\Http\Controllers\PasskeyLoginController::store()` et
 * documentée dans le docblock de {@see SessionAssuranceResolver}.
 */
class SessionAssuranceStandardTierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    public function test_a_login_that_genuinely_completes_a_two_factor_challenge_still_resolves_weak_session_assurance(): void
    {
        $engine = app(Google2FA::class);
        $secret = $engine->generateSecretKey();
        $code = $engine->getCurrentOtp($secret);

        $user = User::factory()->create([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => $code])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);

        // Le login a réellement traversé le défi 2FA (code TOTP valide,
        // vérifié par Fortify lui-même) ; on inspecte maintenant la session
        // résultante, telle quelle, sans aucune hypothèse.
        $request = Request::create('/');
        $request->setLaravelSession($this->app['session.store']);

        $assurance = (new SessionAssuranceResolver)->fromRequest($request);

        $this->assertSame(
            SessionAssurance::Weak,
            $assurance,
            'Si ce test échoue ici, Fortify persiste désormais un signal de '
            .'session distinguant un login 2FA-vérifié : TD-0002-A doit être '
            .'rouverte pour produire réellement `Standard` sur ce signal.'
        );
    }
}
