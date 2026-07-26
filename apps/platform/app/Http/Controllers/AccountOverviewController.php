<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * « Mon espace » (W4, demande Koné 2026-07-26) : équivalent mobile-first
 * de `Settings\ProfileController`, jusqu'ici seul point d'entrée pour
 * modifier nom/e-mail — desktop, en anglais, jamais raccordé à l'onglet
 * « Mon espace » de la navigation mobile (resté désactivé depuis la
 * refonte W4).
 *
 * Ne duplique aucune donnée : mêmes colonnes `users`, mêmes règles de
 * validation ({@see ProfileUpdateRequest}) que `Settings\ProfileController`.
 * N'ajoute aucun champ de profil nouveau (pays, âge, téléphone…) — Person
 * précise volontairement qu'elle ne porte « aucune donnée de profil
 * publicitaire » (P003-A §6, AMD-0009) ; une collecte réelle à finalité
 * publicitaire exigerait une décision de consentement versionnée propre
 * (AMD-0009 §2, §4), hors périmètre de ce lot.
 *
 * Contrôleur distinct plutôt que réutilisation directe de
 * `Settings\ProfileController::update()` : celui-ci redirige toujours vers
 * `profile.edit` (testé, `ProfileUpdateTest`), ce qui romprait la
 * navigation Inertia d'un écran mobile appelant la même action. Même
 * logique de mise à jour, réponse JSON adaptée à un appel `fetch` plutôt
 * qu'à une redirection de page complète (même discipline que
 * `AdvertiserOnboardingForm`, `advertising/overview.tsx`).
 */
class AccountOverviewController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('account/overview', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
        ]);
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
        ]);
    }
}
