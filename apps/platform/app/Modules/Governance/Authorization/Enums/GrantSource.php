<?php

namespace App\Modules\Governance\Authorization\Enums;

enum GrantSource: string
{
    case Direct = 'direct';
    case RoleTemplate = 'role_template';
    case Contract = 'contract';
    case Decision = 'decision';
    case Delegation = 'delegation';
    case Emergency = 'emergency';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
