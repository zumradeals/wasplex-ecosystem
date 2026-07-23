<?php

namespace App\Modules\Governance\Authorization\Support;

/**
 * Résultat explicable de l'évaluation des conditions d'un grant.
 *
 * Distingue un manque isolé de force de session (qui autorise un
 * `step_up_required`) de toute autre insuffisance (qui entraîne un refus).
 */
final readonly class ConditionsMatchResult
{
    private function __construct(
        public bool $satisfied,
        public bool $onlySessionAssuranceInsufficient,
    ) {}

    public static function satisfied(): self
    {
        return new self(true, false);
    }

    public static function sessionAssuranceInsufficient(): self
    {
        return new self(false, true);
    }

    public static function failed(): self
    {
        return new self(false, false);
    }
}
