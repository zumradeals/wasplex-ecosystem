<?php

namespace App\Modules\Alerts\Enums;

/**
 * État d'une restitution (ecosystem/institutions/01 §8) : remise et
 * réception sont deux confirmations distinctes.
 */
enum RestitutionState: string
{
    case Pending = 'pending';
    case CodeIssued = 'code_issued';
    case Delivered = 'delivered';
    case Received = 'received';
    case Completed = 'completed';
    case Disputed = 'disputed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
