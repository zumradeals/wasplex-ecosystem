<?php

namespace App\Modules\Identity\Enums;

/**
 * État explicite d'une appartenance nominative entre une personne, un compte et une organisation.
 *
 * Une appartenance n'accorde par elle-même aucune capacité : cf. ADR-0004 §5, §22.
 */
enum MembershipStatus: string
{
    case Invited = 'invited';
    case Pending = 'pending';
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
