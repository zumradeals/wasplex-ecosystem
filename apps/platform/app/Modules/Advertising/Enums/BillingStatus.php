<?php

namespace App\Modules\Advertising\Enums;

/**
 * Statut anti-double-facturation d'un QualifiedEvent
 * (`01-cycle-creation-valeur.md` §3, §4 invariant 5). `Pending` correspond
 * à la réservation déjà comptabilisée dans le Ledger (§4.2 « pendant
 * contrôle ») ; `Accepted`/`Rejected` sont terminaux.
 */
enum BillingStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
