<?php

namespace App\Modules\Governance\Authorization\Contracts;

use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Identity\Support\AssuranceContext;
use Carbon\CarbonInterface;

/**
 * Requête immuable adressée au moteur d'autorisation (P003-B1 §14).
 *
 * `membershipId`, lorsque fourni, désigne une appartenance revendiquée par
 * l'appelant : le moteur résout et vérifie toujours son organisation réelle
 * et sa liaison personne-compte depuis la base avant de lui faire confiance.
 * Aucun `organization_id` transmis par un client ne devient fiable sans
 * cette validation (P003-B1 §14).
 */
final readonly class AuthorizationRequest
{
    /**
     * @param  list<string>  $territoryCodes
     */
    public function __construct(
        public int $accountUserId,
        public string $personAccountLinkId,
        public ?string $membershipId,
        public string $capabilityKey,
        public ?string $purposeKey,
        public ResourceContext $resource,
        public Operation $operation,
        public ?string $countryCode,
        public array $territoryCodes,
        public Environment $environment,
        public AssuranceContext $assurance,
        public string $correlationId,
        public CarbonInterface $evaluatedAt,
    ) {}
}
