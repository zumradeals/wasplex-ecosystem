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
 * inventé ici). Aucun signal fiable ne distingue aujourd'hui un palier
 * `standard` : cette valeur reste réservée à un événement probant futur
 * (voir TD-0002).
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
