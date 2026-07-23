<?php

namespace App\Modules\Governance\Authorization\Contracts;

/**
 * Motif d'une décision : un code stable et non sensible, plus une
 * explication sûre destinée à l'utilisateur (P003-B1 §15). Le moteur ne
 * renvoie jamais une exception technique comme justification.
 */
final readonly class AuthorizationReason
{
    public function __construct(
        public string $code,
        public string $explanation,
    ) {}
}
