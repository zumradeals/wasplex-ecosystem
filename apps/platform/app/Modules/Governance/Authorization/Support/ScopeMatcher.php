<?php

namespace App\Modules\Governance\Authorization\Support;

use App\Modules\Governance\Authorization\Contracts\ResourceContext;

/**
 * Comparaison déterministe entre une portée de grant et le contexte d'une
 * ressource (P003-B1 §10). Aucun SQL, PHP ou expression libre n'est évalué :
 * seules des comparaisons directes de valeurs sont effectuées.
 *
 * Toutes les dimensions déclarées par la portée doivent correspondre. Une
 * information absente côté contexte n'est jamais interprétée comme
 * illimitée : elle échoue la dimension correspondante.
 */
final class ScopeMatcher
{
    public function matches(ScopePayload $scope, ResourceContext $resource, string $subjectPersonId): bool
    {
        if ($scope->self === true) {
            if ($resource->ownerPersonId === null || $resource->ownerPersonId !== $subjectPersonId) {
                return false;
            }
        }

        if ($scope->organizationId !== null) {
            if ($resource->organizationId === null || $resource->organizationId !== $scope->organizationId) {
                return false;
            }
        }

        if ($scope->resourceType !== null) {
            if ($resource->resourceType === null || $resource->resourceType !== $scope->resourceType) {
                return false;
            }
        }

        if ($scope->resourceIds !== null) {
            if ($resource->resourceId === null || ! in_array($resource->resourceId, $scope->resourceIds, true)) {
                return false;
            }
        }

        if ($scope->countryCode !== null) {
            if ($resource->countryCode === null || $resource->countryCode !== $scope->countryCode) {
                return false;
            }
        }

        if ($scope->territoryCodes !== null) {
            if ($resource->territoryCodes === [] || array_intersect($resource->territoryCodes, $scope->territoryCodes) === []) {
                return false;
            }
        }

        if ($scope->environment !== null) {
            if ($resource->environment === null || $resource->environment !== $scope->environment) {
                return false;
            }
        }

        return true;
    }
}
