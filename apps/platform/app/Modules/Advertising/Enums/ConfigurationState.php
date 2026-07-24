<?php

namespace App\Modules\Advertising\Enums;

/**
 * Cycle de vie d'une ligne de configuration versionnée (ADR-0002 §4, §8).
 * Ce module ne construit pas le registre central ADR-0002 (hors périmètre
 * P005-A) : `SectorClassification` et `AudienceSegmentSizeThreshold`
 * reprennent localement ce cycle minimal (« Brouillon → ... → actif →
 * remplacé », simplifié à draft/active/retired) plutôt que de coder une
 * valeur en dur — voir TD-0004.
 */
enum ConfigurationState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
