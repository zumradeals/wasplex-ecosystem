<?php

namespace App\Modules\Identity\Enums;

/**
 * Axe du statut de représentation d'organisation attaché au compte.
 *
 * Distinct de l'état propre de l'organisation (cf. OrganizationState) : cet axe
 * décrit si, et comment, le compte agit comme représentant d'une organisation.
 */
enum OrganizationStatus: string
{
    case None = 'none';
    case RepresentativePending = 'representative_pending';
    case Authorized = 'authorized';
    case Suspended = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
