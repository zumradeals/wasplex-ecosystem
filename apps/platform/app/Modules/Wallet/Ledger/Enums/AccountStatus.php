<?php

namespace App\Modules\Wallet\Ledger\Enums;

/**
 * Statut d'un compte (ADR-0003 §4). Un compte gelé ou clos refuse tout
 * nouveau posting (déclencheur `postings_enforce_account_rules`) sans que
 * cela n'altère les postings déjà comptabilisés.
 */
enum AccountStatus: string
{
    case Active = 'active';
    case Frozen = 'frozen';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
