<?php

namespace App\Modules\Identity\Enums;

/**
 * État métier du compte. Cf. ecosystem/identite/01-niveaux-et-preuves.md.
 */
enum AccountState: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
