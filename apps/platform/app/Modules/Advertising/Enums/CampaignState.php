<?php

namespace App\Modules\Advertising\Enums;

/**
 * État global d'une Campaign (ADR-0010 §3 : « identité stable, annonceur,
 * état global »), distinct du cycle de vie propre à chaque
 * `CampaignVersion`. Une campagne suspendue ne peut plus engager de
 * nouvelle réservation de budget, mais les réservations déjà engagées
 * suivent leur cycle jusqu'à résolution (ADR-0010 §7).
 */
enum CampaignState: string
{
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
