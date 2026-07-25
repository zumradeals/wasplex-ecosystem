<?php

namespace App\Modules\Governance\Configuration\Enums;

/**
 * Décision nominative d'un approbateur sur une `ValueVersion` (ADR-0002 §8).
 */
enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
