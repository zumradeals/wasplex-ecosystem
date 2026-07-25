<?php

namespace App\Modules\Governance\Configuration\Services;

use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueVersionState;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Models\ValueVersion;
use App\Modules\Governance\Configuration\Services\Exceptions\NoActiveConfigurationException;

/**
 * Résolution d'une configuration active par clé stable (ADR-0002 §5, §7.3).
 *
 * Échoue toujours fermé : ni `Definition` inactive ni absence de
 * `ValueVersion` active ne retournent jamais une valeur par défaut
 * silencieuse — même discipline que
 * `App\Modules\Advertising\Services\AudienceSegmentGuard::activeThreshold()`.
 *
 * Résolution simplifiée au noyau (W2) : une seule portée globale par
 * definition (`value_versions_one_active_per_definition`). La hiérarchie
 * complète d'ADR-0002 §5 (invariants > loi > publication globale >
 * publication nationale > version produit > contrat > paramètres de
 * l'opération) reste différée — porte de reprise avant qu'un module
 * n'exige une résolution par pays ou par contrat.
 */
class ConfigurationResolver
{
    /**
     * @throws NoActiveConfigurationException Aucune definition active ou aucune valeur active pour cette clé.
     */
    public function resolve(string $stableKey): ValueVersion
    {
        $definition = Definition::query()
            ->where('stable_key', $stableKey)
            ->where('state', DefinitionState::Active)
            ->first();

        if ($definition === null) {
            throw new NoActiveConfigurationException("aucune definition active pour la clé « {$stableKey} »");
        }

        $active = ValueVersion::query()
            ->where('definition_id', $definition->id)
            ->where('state', ValueVersionState::Active)
            ->first();

        if ($active === null) {
            throw new NoActiveConfigurationException("aucune valeur active pour la definition « {$stableKey} »");
        }

        return $active;
    }

    /**
     * @throws NoActiveConfigurationException Aucune definition active ou aucune valeur active pour cette clé.
     */
    public function value(string $stableKey): mixed
    {
        return $this->resolve($stableKey)->value;
    }
}
