<?php

namespace App\Modules\Identity\Enums;

/**
 * Axe de preuve du canal de contact (e-mail). Cf. ecosystem/identite/01-niveaux-et-preuves.md.
 */
enum ContactAssurance: string
{
    case Unconfirmed = 'unconfirmed';
    case Confirmed = 'confirmed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
