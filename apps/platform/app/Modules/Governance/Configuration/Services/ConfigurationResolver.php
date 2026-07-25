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

    /**
     * Résolution épinglée par (clé stable, version) — pas nécessairement la
     * version actuellement active. ADR-0002 §6 : « chaque événement utilise
     * la version diffusée au moment de son exécution » ; une campagne fige
     * `pricing_configuration_key`/`version` à son approbation, qui doit
     * rester résolvable même après qu'une version plus récente ait
     * remplacé celle épinglée (`replaced`).
     *
     * `draft`/`in_review`/`rejected`/`cancelled` sont toujours refusés :
     * seule une version ayant réellement été mise en effet un jour
     * (`active` ou `replaced`) constitue une référence légitime — jamais
     * une proposition qui n'a jamais été activée.
     *
     * @throws NoActiveConfigurationException Aucune definition ne porte cette clé, ou cette version n'a jamais été mise en effet.
     */
    public function resolveExactVersion(string $stableKey, int $version): ValueVersion
    {
        $definition = Definition::query()
            ->where('stable_key', $stableKey)
            ->first();

        if ($definition === null) {
            throw new NoActiveConfigurationException("aucune definition pour la clé « {$stableKey} »");
        }

        $pinned = ValueVersion::query()
            ->where('definition_id', $definition->id)
            ->where('version', $version)
            ->whereIn('state', [ValueVersionState::Active, ValueVersionState::Replaced])
            ->first();

        if ($pinned === null) {
            throw new NoActiveConfigurationException(
                "aucune version {$version} légitimement mise en effet pour la definition « {$stableKey} »"
            );
        }

        return $pinned;
    }
}
