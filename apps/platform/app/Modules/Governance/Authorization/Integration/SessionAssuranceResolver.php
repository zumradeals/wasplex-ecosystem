<?php

namespace App\Modules\Governance\Authorization\Integration;

use App\Modules\Identity\Enums\SessionAssurance;
use Illuminate\Http\Request;

/**
 * Résout la force réellement prouvée de la session courante (P003-B2 §B).
 *
 * Ne devine jamais un niveau intermédiaire : par défaut, une session
 * authentifiée reste `weak`. Elle n'est élevée à `strong` que si Laravel a
 * lui-même constaté, pendant cette session, une reconfirmation récente du
 * mot de passe (`auth.password_confirmed_at`, dans la fenêtre déjà
 * configurée par `config('auth.password_timeout')` — aucun seuil n'est
 * inventé ici).
 *
 * ## Hiérarchie complète (Weak < Standard < Strong)
 *
 * - **Weak** : session authentifiée ordinaire, sans événement probant
 *   constaté pendant cette session précise. Palier par défaut — toute
 *   session commence ici et y reste tant qu'aucune des conditions
 *   ci-dessous n'est remplie.
 * - **Standard** (jamais produit aujourd'hui — voir ci-dessous) :
 *   devrait refléter une session dont le *login* a lui-même traversé un
 *   second facteur réellement vérifié (code 2FA confirmé, ou passkey),
 *   distinct d'une simple reconfirmation mid-session.
 * - **Strong** : reconfirmation mid-session récente du mot de passe
 *   (`auth.password_confirmed_at`), dans la fenêtre de
 *   `config('auth.password_timeout')`. Couvre aussi, de façon incidente
 *   mais correcte, la reconfirmation mid-session par passkey : le paquet
 *   `laravel/passkeys` appelle `Session::passwordConfirmed()` — donc écrit
 *   exactement la même clé de session — depuis
 *   `PasskeyConfirmationController::store()`.
 *
 * ## TD-0002-A — pourquoi `Standard` reste hors d'atteinte
 *
 * Vérification empirique du pipeline de login réel (pas une supposition) :
 *
 * - Login 2FA (`Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController::store()`) :
 *   après validation du code, appelle `$guard->login()` puis
 *   `session()->regenerate()`. Aucune clé de session n'est écrite pour
 *   marquer la réussite. Pire pour toute tentative de déduction indirecte :
 *   `TwoFactorLoginRequest::hasValidCode()`/`validRecoveryCode()`
 *   suppriment explicitement `login.id` de la session dès que le code est
 *   validé (`session()->forget('login.id')`) — la présence ou l'absence de
 *   cette clé de stadification ne prouve donc jamais un login 2FA réussi,
 *   et sa disparition est même le comportement normal du succès.
 * - Login passkey (`Laravel\Passkeys\Http\Controllers\PasskeyLoginController::store()`) :
 *   appelle `VerifyPasskey`, `$guard->login()`, puis
 *   `session()->regenerate()`. Aucune clé de session n'est écrite non plus.
 * - L'événement `Laravel\Passkeys\Events\PasskeyVerified` est déclenché à
 *   l'identique par le login passkey ET par
 *   `PasskeyConfirmationController::store()` (reconfirmation mid-session,
 *   qui doit rester `Strong`) : cet événement ne permet donc pas, à lui
 *   seul, de distinguer les deux cas sans inspecter un état
 *   d'authentification qui n'existe pas encore au moment du login.
 * - Le pipeline de login ordinaire
 *   (`AuthenticatedSessionController::loginPipeline()` :
 *   `EnsureLoginIsNotThrottled` → `CanonicalizeUsername` →
 *   `RedirectIfTwoFactorAuthenticatable` → `AttemptToAuthenticate` →
 *   `PrepareAuthenticatedSession`) ne fait, lui non plus, que régénérer la
 *   session et vider le limiteur de tentatives — identique que 2FA ait été
 *   utilisé ou non pour atteindre ce point.
 *
 * Conclusion : ni Fortify, ni `laravel/passkeys`, ne persistent par
 * eux-mêmes un signal de session distinguant un login 2FA/passkey vérifié
 * d'un login par mot de passe seul. Ajouter nous-mêmes un tel marqueur
 * (par exemple via un listener sur `ValidTwoFactorAuthenticationCodeProvided`
 * ou `PasskeyVerified`) reviendrait à inventer un mécanisme que Fortify ne
 * fournit pas lui-même — précisément ce que ce resolver s'interdit
 * (« jamais une déduction indirecte ou un flag que l'on positionnerait
 * soi-même »). `Standard` ne peut donc pas être produit aujourd'hui ; cette
 * limitation reste documentée et acceptée en TD-0002-A plutôt que
 * contournée. Cette méthode ne renvoie donc jamais {@see SessionAssurance::Standard}.
 */
final class SessionAssuranceResolver
{
    public function fromRequest(Request $request): SessionAssurance
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        if (is_int($confirmedAt)) {
            $timeout = (int) config('auth.password_timeout', 10800);

            if ($confirmedAt > time() - $timeout) {
                return SessionAssurance::Strong;
            }
        }

        return SessionAssurance::Weak;
    }
}
