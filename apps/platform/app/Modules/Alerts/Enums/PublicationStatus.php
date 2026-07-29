<?php

namespace App\Modules\Alerts\Enums;

/**
 * Statut d'une projection publique (ecosystem/alertes/03 §2).
 */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Withdrawn = 'withdrawn';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
