<?php

namespace App\Modules\Governance\Authorization\Enums;

enum GrantEffect: string
{
    case Allow = 'allow';
    case ReadOnly = 'read_only';
    case Masked = 'masked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
