<?php

namespace App\Modules\Wallet\Ledger\Enums;

/**
 * État d'une transaction comptable (ADR-0003 §15). Ce noyau (P004-A) ne
 * comptabilise que des transactions déjà décidées par le module appelant :
 * il n'existe donc, pour l'instant, qu'un seul état matérialisé. Les états
 * transitoires d'une intention non encore comptabilisée (par exemple un
 * retrait « demandé » avant réservation, ADR-0003 §9) appartiennent aux
 * objets métier de leurs futurs modules (PaymentIntent, etc.), pas à cette
 * ligne du ledger — voir TD-0003.
 *
 * Une transaction comptabilisée ne change jamais d'état après création : ce
 * n'est pas un champ de cycle de vie comme `Grant::state`, c'est une valeur
 * fixée une fois pour toutes par le déclencheur d'immutabilité.
 */
enum LedgerTransactionState: string
{
    case Posted = 'posted';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
