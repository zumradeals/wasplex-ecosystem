<?php

namespace App\Modules\Governance\Authorization\Contracts;

/**
 * Obligation attachée à une décision, par exemple le masquage de certains
 * champs ou une exécution en lecture seule (P003-B1 §15).
 */
final readonly class AuthorizationObligation
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $type,
        public array $payload = [],
    ) {}
}
