<?php

namespace App\Modules\Governance\Authorization\Support;

use App\Modules\Identity\Enums\SessionAssurance;

/**
 * Résultat explicable de l'évaluation des conditions d'un grant.
 *
 * Distingue un manque isolé de force de session (qui autorise un
 * `step_up_required`) de toute autre insuffisance (qui entraîne un refus).
 * `requiredSessionAssurance` porte le palier effectivement exigé (le plus
 * exigeant entre la capacité et le grant, TD-0002-B) ; il n'est jamais
 * renseigné hors du cas d'insuffisance isolée de session.
 */
final readonly class ConditionsMatchResult
{
    private function __construct(
        public bool $satisfied,
        public bool $onlySessionAssuranceInsufficient,
        public ?SessionAssurance $requiredSessionAssurance = null,
    ) {}

    public static function satisfied(): self
    {
        return new self(true, false);
    }

    public static function sessionAssuranceInsufficient(SessionAssurance $required): self
    {
        return new self(false, true, $required);
    }

    public static function failed(): self
    {
        return new self(false, false);
    }
}
