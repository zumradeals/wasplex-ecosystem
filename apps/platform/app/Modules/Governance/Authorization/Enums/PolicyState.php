<?php

namespace App\Modules\Governance\Authorization\Enums;

enum PolicyState: string
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
