<?php

namespace App\Modules\Identity\Enums;

/**
 * État métier propre à l'organisation elle-même (cycle de vie de l'enregistrement).
 *
 * Distinct de OrganizationStatus, qui décrit la représentation d'un compte
 * au sein d'une organisation.
 */
enum OrganizationState: string
{
    case Draft = 'draft';
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
