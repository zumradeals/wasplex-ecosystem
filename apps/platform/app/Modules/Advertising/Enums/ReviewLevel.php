<?php

namespace App\Modules\Advertising\Enums;

/**
 * Niveau de revue d'un secteur (`01-classification-secteurs-et-contenus.md`
 * §4 « niveau de revue »). Un niveau `Enhanced` impose la séparation des
 * tâches à l'approbation d'une CampaignVersion (ADR-0010 §5 : « l'auteur
 * d'une campagne ne peut jamais être son propre approbateur pour une
 * campagne à risque élevé »), sur le modèle de
 * `GrantManager::activate()` (TD-0001-A).
 */
enum ReviewLevel: string
{
    case Standard = 'standard';
    case Enhanced = 'enhanced';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
