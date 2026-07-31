<?php

namespace App\Modules\Advertising\Projections;

use App\Modules\Advertising\Models\Campaign;
use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Total financé à ce jour pour cette campagne (instruction explicite
     * du fondateur, 2026-07-31) : somme des crédits déposés sur le compte
     * disponible par les seules transactions de type
     * `advertising_campaign_funding` ({@see CampaignBudgetService::fund()}).
     * Ne PAS confondre avec `available() + reserved() + consumed()` : ce
     * total n'est pas invariant, la répartition (`acceptQualifiedEvent()`)
     * fait sortir l'argent consommé vers des comptes tiers (bénéficiaire,
     * revenu Wasplex), donc `consumed()` ne retient rien après
     * répartition — seule cette somme des financements reçus grandit de
     * façon monotone et sert de base fiable au dimensionnement des
     * cagnottes par type économique.
     */
    public function totalFunded(Campaign $campaign): int
    {
        return (int) DB::table('ledger.postings')
            ->join('ledger.ledger_transactions', 'ledger.ledger_transactions.id', '=', 'ledger.postings.ledger_transaction_id')
            ->where('ledger.postings.account_id', $campaign->available_account_id)
            ->where('ledger.postings.direction', 'credit')
            ->where('ledger.ledger_transactions.type', 'advertising_campaign_funding')
            ->sum('ledger.postings.amount');
    }
}
