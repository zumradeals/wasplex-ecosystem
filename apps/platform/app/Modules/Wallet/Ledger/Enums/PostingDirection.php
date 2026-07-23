<?php

namespace App\Modules\Wallet\Ledger\Enums;

/**
 * Sens explicite d'un posting (ADR-0003 §15, architecture/05 §"Règles
 * structurelles"). Un posting n'a jamais de sens implicite déduit du signe
 * d'un montant : le montant est toujours un entier strictement positif.
 */
enum PostingDirection: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
