<?php

namespace App\Modules\Wallet\Ledger\Enums;

use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;

/**
 * Nature comptable d'un compte (ADR-0003 §4, tâche P004-A §3.A). Détermine
 * le sens naturel du solde reconstruit par {@see AccountBalanceProjection}.
 */
enum AccountNature: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
