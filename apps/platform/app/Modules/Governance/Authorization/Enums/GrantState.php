<?php

namespace App\Modules\Governance\Authorization\Enums;

enum GrantState: string
{
    case Proposed = 'proposed';
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Revoked = 'revoked';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
