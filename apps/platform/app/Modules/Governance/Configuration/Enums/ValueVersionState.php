<?php

namespace App\Modules\Governance\Configuration\Enums;

/**
 * Cycle de vie d'une `ValueVersion` (ADR-0002 §4, §8), réduit au noyau
 * (W2) : `simulated` et `scheduled` sont volontairement absents (aucune
 * Simulation ni prise d'effet programmée à date future dans ce lot — voir
 * la migration de création de la table).
 */
enum ValueVersionState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Active = 'active';
    case Replaced = 'replaced';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
