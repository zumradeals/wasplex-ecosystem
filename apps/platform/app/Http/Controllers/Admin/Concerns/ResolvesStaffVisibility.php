<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Modules\Advertising\Http\Controllers\Admin\ModerationOverviewController;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Prologue partagé par les écrans du portail personnel Wasplex
 * (P0-Admin) : résoudre le sujet, puis vérifier une VISIBILITÉ de section
 * par capacité — jamais une autorisation d'action, toujours déjà décidée
 * par {@see AuthorizationGate}
 * au moment de l'action réelle (même invariant documenté sur
 * {@see ModerationOverviewController}
 * avant extraction). Aucun rôle « admin » générique n'existe ni n'est créé
 * ici : chaque section reste indépendamment gouvernée par sa propre
 * capacité (UX-0001 §8 « Chaque personne ne voit que les files
 * correspondant à ses capacités »).
 *
 * Chaque contrôleur hôte doit déclarer par constructeur
 * `$subjectResolver` (même type que les contrôleurs Advertising
 * existants).
 */
trait ResolvesStaffVisibility
{
    /**
     * @return array{link: PersonAccountLink}|Response
     */
    private function resolveStaffSubject(Request $request, string $component, mixed $deniedProps): array|Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render($component, $deniedProps('subject_not_resolved'));
        }

        return ['link' => $subject->personAccountLink];
    }

    /**
     * Vérification de visibilité seule (voir docblock du trait) : même
     * base de requête que les étapes 5-6 de
     * {@see AuthorizationEngine::evaluate()},
     * sans l'appariement de portée qui suit.
     */
    private function hasActiveStaffGrant(PersonAccountLink $link, string $capabilityKey): bool
    {
        $capability = CapabilityDefinition::query()
            ->where('stable_key', $capabilityKey)
            ->where('state', 'active')
            ->first();

        if ($capability === null) {
            return false;
        }

        $now = Carbon::now();

        return Grant::query()
            ->where('capability_definition_id', $capability->id)
            ->where('person_account_link_id', $link->id)
            ->where('state', GrantState::Active->value)
            ->where('valid_from', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $now);
            })
            ->exists();
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    private function staffAccessFor(bool $allowed): array
    {
        return ['allowed' => $allowed, 'reason' => $allowed ? null : 'no_active_grant'];
    }
}
