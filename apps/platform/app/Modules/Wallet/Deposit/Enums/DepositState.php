<?php

namespace App\Modules\Wallet\Deposit\Enums;

/**
 * Machine d'états du dépôt (ecosystem/wallet/05 §2 ; AMD-0017). `Completed`
 * et `Failed` sont terminaux : aucune transition n'en repart, une
 * correction passe par une contre-écriture Ledger (article 17, alinéa 9),
 * jamais par une réécriture d'état.
 */
enum DepositState: string
{
    case Draft = 'draft';
    case AwaitingProvider = 'awaiting_provider';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case UnknownReconciliation = 'unknown_reconciliation';
}
