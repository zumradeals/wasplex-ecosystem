<?php

namespace App\Modules\Advertising\Enums;

/**
 * Machine d'états d'un achat d'abonnement (mirroir exact de
 * {@see AdvertiserWalletDepositState}). `Completed`/`Failed` sont
 * terminaux.
 */
enum SubscriptionPurchaseState: string
{
    case Draft = 'draft';
    case AwaitingProvider = 'awaiting_provider';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case UnknownReconciliation = 'unknown_reconciliation';
}
