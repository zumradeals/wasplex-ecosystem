<?php

namespace App\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use Illuminate\Support\Str;

/**
 * Construit une {@see AuthorizationRequest} à partir d'un sujet déjà résolu
 * côté serveur (P003-B2 §B).
 *
 * Ne devine jamais une donnée métier absente : la capacité, l'opération,
 * la ressource et l'environnement réellement applicables doivent toujours
 * être fournis explicitement par le module propriétaire. Une absence n'est
 * jamais convertie en portée illimitée.
 */
final class AuthorizationRequestFactory
{
    /**
     * @param  list<string>  $territoryCodes
     */
    public function make(
        AuthenticatedSubject $subject,
        string $capabilityKey,
        Operation $operation,
        ResourceContext $resource,
        Environment $environment,
        ?string $purposeKey = null,
        ?string $countryCode = null,
        array $territoryCodes = [],
        ?string $correlationId = null,
    ): AuthorizationRequest {
        return new AuthorizationRequest(
            accountUserId: $subject->account->id,
            personAccountLinkId: $subject->personAccountLink->id,
            membershipId: $subject->membership?->id,
            capabilityKey: $capabilityKey,
            purposeKey: $purposeKey,
            resource: $resource,
            operation: $operation,
            countryCode: $countryCode,
            territoryCodes: $territoryCodes,
            environment: $environment,
            assurance: $subject->assurance,
            correlationId: $correlationId ?? (string) Str::uuid(),
            evaluatedAt: now(),
        );
    }
}
