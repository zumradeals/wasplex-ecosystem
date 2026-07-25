<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Services\ConfigurationResolver;
use App\Modules\Governance\Configuration\Services\Exceptions\NoActiveConfigurationException;

/**
 * Résout le prix applicable d'un événement qualifié à partir de la
 * configuration de prix épinglée par la `CampaignVersion`
 * (`pricing_configuration_key`/`pricing_configuration_version`) via le
 * registre central (W2, ADR-0002 §6, §8) — jamais un montant fourni par
 * l'appelant (P005-A/P005-F, TD-0004 « event.submit »).
 *
 * Ferme la dette documentée sur `event.submit` (migration
 * `2026_07_25_100009`) : « ce n'est pas encore le vrai flux self-service
 * utilisateur final ; ce dernier suppose un moteur de prix versionné ».
 * Ce service EST ce moteur — volontairement minimal (noyau, TD-0006) :
 * un prix de base fixe par `CampaignVersion`, sans coefficient de format,
 * de fréquence, de rareté de segment ni de période (ADR-0010 §6, §7,
 * hors périmètre de ce lot).
 */
class QualifiedEventPricingResolver
{
    public function __construct(
        private readonly ConfigurationResolver $configurationResolver,
    ) {}

    /**
     * @throws PricingConfigurationNotResolvableException La CampaignVersion n'a pas de configuration de prix épinglée, ou celle-ci n'est plus résolvable, ou n'est pas un montant entier.
     */
    public function resolveBasePrice(CampaignVersion $version): int
    {
        $key = $version->pricing_configuration_key;
        $configurationVersion = $version->pricing_configuration_version;

        if ($key === null || $configurationVersion === null) {
            throw new PricingConfigurationNotResolvableException(
                "cette CampaignVersion n'a épinglé aucune configuration de prix (pricing_configuration_key/version)"
            );
        }

        try {
            $pinned = $this->configurationResolver->resolveExactVersion($key, $configurationVersion);
        } catch (NoActiveConfigurationException $exception) {
            throw new PricingConfigurationNotResolvableException(
                "configuration de prix introuvable ou jamais mise en effet : {$key}#{$configurationVersion}",
                previous: $exception,
            );
        }

        if ($pinned->definition->value_type !== ValueType::Integer) {
            throw new PricingConfigurationNotResolvableException(
                "la definition « {$key} » n'est pas un montant entier (value_type={$pinned->definition->value_type->value})"
            );
        }

        return $pinned->value;
    }
}
