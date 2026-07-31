<?php

namespace App\Modules\Advertising\Projections;

use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Services\AdvertiserWalletLedgerAccounts;
use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Models\Account;
use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;

/**
 * Solde Wallet d'un annonceur, par devise (instruction explicite du
 * fondateur, 2026-07-31) — même gabarit exact que
 * {@see PersonBalanceProjection}.
 * Ne lit jamais un champ de solde stocké : reconstruit depuis les comptes
 * `advertiser_wallet` provisionnés par
 * {@see AdvertiserWalletLedgerAccounts}.
 * Un annonceur sans aucun dépôt encore reçu obtient une liste vide, jamais
 * une erreur.
 */
class AdvertiserWalletBalanceProjection
{
    public function __construct(
        private readonly AccountBalanceProjection $accountBalance,
    ) {}

    /**
     * @return list<array{currency: string, available: int}>
     */
    public function forAdvertiser(AdvertiserProfile $advertiser): array
    {
        $accounts = Account::query()
            ->where('purpose', AccountPurpose::AdvertiserWallet)
            ->where('compartment', $advertiser->id)
            ->orderBy('currency')
            ->get();

        return array_values($accounts
            ->map(fn (Account $account): array => [
                'currency' => $account->currency,
                'available' => $this->accountBalance->currentBalance($account),
            ])
            ->all());
    }
}
