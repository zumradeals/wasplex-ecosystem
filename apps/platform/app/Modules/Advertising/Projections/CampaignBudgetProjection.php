<?php

namespace App\Modules\Advertising\Projections;

use App\Modules\Advertising\Models\Campaign;
use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;

/**
 * CampaignBudget (ADR-0010 §3) : disponible/réservé/consommé ne sont
 * jamais des colonnes mutables, seulement des projections reconstruites
 * depuis les trois comptes `ledger.accounts` dédiés de la campagne.
 * Publicité ne maintient aucun solde d'autorité — seul le Ledger en a un.
 */
class CampaignBudgetProjection
{
    public function __construct(
        private readonly AccountBalanceProjection $accountBalance,
    ) {}

    public function available(Campaign $campaign): int
    {
        return $this->accountBalance->currentBalance($campaign->availableAccount);
    }

    public function reserved(Campaign $campaign): int
    {
        return $this->accountBalance->currentBalance($campaign->reservedAccount);
    }

    public function consumed(Campaign $campaign): int
    {
        return $this->accountBalance->currentBalance($campaign->consumedAccount);
    }
}
