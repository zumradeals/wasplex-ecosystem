<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\PersonEconomicTypeAssignment;
use App\Modules\Advertising\Models\PersonSubscription;
use RuntimeException;

/**
 * Résout le type économique applicable à une personne (instruction
 * explicite du fondateur, 2026-07-31), dans cet ordre :
 *
 * 1. Affectation manuelle explicite (`PersonEconomicTypeAssignment`) —
 *    correction ou cas particulier décidé par le personnel habilité,
 *    l'emporte toujours sur un abonnement.
 * 2. Abonnement actif et non expiré (`PersonSubscription::isActive()`) —
 *    le chemin normal (docs/02 §3 : « chaque type possède un ou plusieurs
 *    plans d'abonnement associés »). Un abonnement expiré n'est jamais
 *    lu ici : aucun balayage périodique ne le marque « expired » ailleurs
 *    (ADR-0003 §19), l'expiration est un simple calcul sur `ends_at` à
 *    l'instant de la résolution.
 * 3. Type par défaut actif — personne sans abonnement ni affectation.
 *
 * Échec fermé si aucun type par défaut n'est configuré (même garde que
 * {@see AudienceSegmentGuard::activeThreshold()}, ADR-0002 §7.3) — ne
 * devrait jamais se produire hors d'une base fraîchement migrée sans le
 * type « standard » seedé (migration `2026_07_31_200002`).
 */
class EconomicTypeResolver
{
    public function forPerson(string $personId): EconomicType
    {
        $assignment = PersonEconomicTypeAssignment::query()
            ->where('person_id', $personId)
            ->with('economicType')
            ->first();

        if ($assignment !== null && $assignment->economicType->state === ConfigurationState::Active) {
            return $assignment->economicType;
        }

        $subscription = PersonSubscription::query()
            ->where('person_id', $personId)
            ->with('subscriptionPlan.economicType')
            ->first();

        if ($subscription !== null && $subscription->isActive() && $subscription->subscriptionPlan->economicType->state === ConfigurationState::Active) {
            return $subscription->subscriptionPlan->economicType;
        }

        $default = EconomicType::query()
            ->where('is_default', true)
            ->where('state', ConfigurationState::Active)
            ->first();

        if ($default === null) {
            throw new RuntimeException('aucun type économique par défaut actif (ADR-0002 §7.3)');
        }

        return $default;
    }
}
