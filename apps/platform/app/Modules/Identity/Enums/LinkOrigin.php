<?php

namespace App\Modules\Identity\Enums;

/**
 * Origine de la liaison personne-compte, conservée conformément à ADR-0006 §5 et §17.
 */
enum LinkOrigin: string
{
    case Registration = 'registration';
    case Migration = 'migration';
    case SupportReview = 'support_review';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
