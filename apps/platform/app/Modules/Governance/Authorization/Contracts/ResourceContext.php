<?php

namespace App\Modules\Governance\Authorization\Contracts;

use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Support\ScopeMatcher;

/**
 * Ressource visée par une requête d'autorisation (P003-B1 §14).
 *
 * Une dimension absente ici n'est jamais interprétée comme illimitée par le
 * {@see ScopeMatcher} : elle
 * échoue toute restriction de portée qui la déclarerait explicitement.
 */
final readonly class ResourceContext
{
    /**
     * @param  list<string>  $territoryCodes
     */
    public function __construct(
        public ?string $resourceType,
        public ?string $resourceId,
        public ?string $organizationId,
        public ?string $ownerPersonId,
        public ?string $countryCode,
        public array $territoryCodes,
        public ?Environment $environment,
    ) {}
}
