<?php

namespace App\Modules\Advertising\Enums;

/**
 * Machine d'états du financement de campagne (mirroir exact de
 * `App\Modules\Wallet\Deposit\Enums\DepositState`). `Completed`/`Failed`
 * sont terminaux : aucune transition n'en repart, une correction passe par
 * une contre-écriture Ledger, jamais par une réécriture d'état.
 */
enum CampaignFundingState: string
{
    case Draft = 'draft';
    case AwaitingProvider = 'awaiting_provider';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case UnknownReconciliation = 'unknown_reconciliation';
}
