<?php

namespace App\Modules\Advertising\Enums;

/**
 * Machine d'états d'un dépôt Wallet annonceur (mirroir exact de
 * {@see CampaignFundingState} / `App\Modules\Wallet\Deposit\Enums\DepositState`).
 * `Completed`/`Failed` sont terminaux : aucune transition n'en repart, une
 * correction passe par une contre-écriture Ledger, jamais par une réécriture
 * d'état.
 */
enum AdvertiserWalletDepositState: string
{
    case Draft = 'draft';
    case AwaitingProvider = 'awaiting_provider';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case UnknownReconciliation = 'unknown_reconciliation';
}
