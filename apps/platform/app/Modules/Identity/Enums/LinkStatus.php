<?php

namespace App\Modules\Identity\Enums;

/**
 * État de la liaison historisée entre une personne et un compte.
 */
enum LinkStatus: string
{
    case Active = 'active';
    case Superseded = 'superseded';
    case Disputed = 'disputed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
