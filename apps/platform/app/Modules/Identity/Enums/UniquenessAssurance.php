<?php

namespace App\Modules\Identity\Enums;

/**
 * Axe d'unicité de la personne, distinct de l'identité vérifiée. Cf. AMD-0010.
 */
enum UniquenessAssurance: string
{
    case Unknown = 'unknown';
    case Probable = 'probable';
    case Sufficient = 'sufficient';
    case Disputed = 'disputed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
