<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Services\Exceptions\ForbiddenTargetingCriterionException;

/**
 * Liste fermée des caractéristiques sensibles refusées comme critère de
 * ciblage commercial ordinaire (AMD-0009 §14, reprise littérale) :
 * « Santé, handicap, détresse, urgence, dette, pauvreté individuelle,
 * religion, opinion politique, origine ethnique, orientation sexuelle,
 * statut de victime, vulnérabilité familiale, justice, position intime et
 * données de mineurs ». Aucune caractéristique sensible n'est déduite ou
 * exploitée commercialement même si elle pourrait être estimée
 * (AMD-0009 §16) : cette liste n'est donc jamais étendue par une
 * configuration administrable.
 */
final class AudienceCriteria
{
    /**
     * @var list<string>
     */
    private const FORBIDDEN_KEYS = [
        'health', 'disability', 'distress', 'emergency', 'debt', 'individual_poverty',
        'religion', 'political_opinion', 'ethnicity', 'sexual_orientation',
        'victim_status', 'family_vulnerability', 'justice', 'intimate_status', 'minor_data',
    ];

    /**
     * @param  array<string, mixed>  $criteria
     *
     * @throws ForbiddenTargetingCriterionException
     */
    public static function assertAllowed(array $criteria): void
    {
        foreach (array_keys($criteria) as $key) {
            if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                throw new ForbiddenTargetingCriterionException(
                    "critère de ciblage interdit : {$key} n'est jamais un critère commercial ordinaire (AMD-0009 §14)"
                );
            }
        }
    }
}
