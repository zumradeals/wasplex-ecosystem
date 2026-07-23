<?php

namespace App\Modules\Governance\Authorization\Enums;

enum GrantEventType: string
{
    case Proposed = 'proposed';
    case Activated = 'activated';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Expired = 'expired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
