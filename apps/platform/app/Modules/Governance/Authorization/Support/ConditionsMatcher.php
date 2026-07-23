<?php

namespace App\Modules\Governance\Authorization\Support;

use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Support\AssuranceContext;

/**
 * Évaluation explicable des conditions structurées d'un grant (P003-B1 §11).
 *
 * Un manque isolé de force de session est distingué de toute autre
 * insuffisance, afin de permettre au moteur de retourner `step_up_required`
 * plutôt qu'un refus définitif lorsque seule la session est en cause.
 *
 * Le niveau de session effectivement exigé est toujours le plus exigeant
 * entre le minimum déclaré par la capacité et celui éventuellement déclaré
 * par les conditions du grant : un grant ne peut jamais abaisser le minimum
 * imposé par sa capacité (P003-B1.1 §1).
 */
final class ConditionsMatcher
{
    /**
     * @var array<string, int>
     */
    private const CONTACT_ASSURANCE_RANK = [
        'unconfirmed' => 0,
        'confirmed' => 1,
    ];

    /**
     * @var array<string, int>
     */
    private const IDENTITY_ASSURANCE_RANK = [
        'undeclared' => 0,
        'declared' => 1,
        'verified' => 2,
        'reinforced' => 3,
    ];

    /**
     * @var array<string, int>
     */
    private const UNIQUENESS_ASSURANCE_RANK = [
        'disputed' => -1,
        'unknown' => 0,
        'probable' => 1,
        'sufficient' => 2,
    ];

    /**
     * @var array<string, int>
     */
    private const SESSION_ASSURANCE_RANK = [
        'weak' => 0,
        'standard' => 1,
        'strong' => 2,
    ];

    public function evaluate(
        ConditionsPayload $conditions,
        AssuranceContext $assurance,
        SessionAssurance $capabilityMinimumSessionAssurance,
    ): ConditionsMatchResult {
        if ($conditions->minimumContactAssurance !== null
            && ! $this->meets(self::CONTACT_ASSURANCE_RANK, $assurance->contactAssurance->value, $conditions->minimumContactAssurance->value)) {
            return ConditionsMatchResult::failed();
        }

        if ($conditions->minimumIdentityAssurance !== null
            && ! $this->meets(self::IDENTITY_ASSURANCE_RANK, $assurance->identityAssurance->value, $conditions->minimumIdentityAssurance->value)) {
            return ConditionsMatchResult::failed();
        }

        if ($conditions->minimumUniquenessAssurance !== null
            && ! $this->meets(self::UNIQUENESS_ASSURANCE_RANK, $assurance->uniquenessAssurance->value, $conditions->minimumUniquenessAssurance->value)) {
            return ConditionsMatchResult::failed();
        }

        if ($conditions->requiredOrganizationStatus !== null
            && $assurance->organizationStatus !== $conditions->requiredOrganizationStatus) {
            return ConditionsMatchResult::failed();
        }

        // Le niveau effectif est le plus exigeant entre le plancher de la
        // capacité et celui du grant : un grant ne peut jamais abaisser le
        // minimum de sa capacité (P003-B1.1 §1).
        $capabilityFloor = self::SESSION_ASSURANCE_RANK[$capabilityMinimumSessionAssurance->value];
        $conditionFloor = $conditions->minimumSessionAssurance !== null
            ? self::SESSION_ASSURANCE_RANK[$conditions->minimumSessionAssurance->value]
            : $capabilityFloor;
        $effectiveFloor = max($capabilityFloor, $conditionFloor);

        if (self::SESSION_ASSURANCE_RANK[$assurance->sessionAssurance->value] < $effectiveFloor) {
            return ConditionsMatchResult::sessionAssuranceInsufficient();
        }

        return ConditionsMatchResult::satisfied();
    }

    /**
     * @param  array<string, int>  $ranks
     */
    private function meets(array $ranks, string $actual, string $minimum): bool
    {
        return $ranks[$actual] >= $ranks[$minimum];
    }
}
