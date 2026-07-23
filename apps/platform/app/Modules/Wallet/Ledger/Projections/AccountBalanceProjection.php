<?php

namespace App\Modules\Wallet\Ledger\Projections;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * Solde courant d'un compte, entièrement reconstruit depuis
 * `ledger.postings` (ADR-0003 §1, §8, §15, §19 : « aucun solde n'est une
 * donnée modifiable »). Aucun champ de solde n'est jamais stocké sur
 * {@see Account} : cette classe est la seule source de lecture du solde.
 *
 * Le sens naturel du solde suit la convention comptable standard de la
 * partie double, déterminée uniquement par la nature déjà posée du compte
 * (ADR-0003 §4) : un compte d'actif ou de charge croît au débit, un compte
 * de passif ou de revenu croît au crédit. Ce n'est pas une formule métier
 * inventée, c'est la définition même du débit et du crédit.
 */
class AccountBalanceProjection
{
    public function currentBalance(Account $account): int
    {
        $totals = DB::table('ledger.postings')
            ->where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END), 0)::bigint as total_debit")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0)::bigint as total_credit")
            ->first();

        $totalDebit = (int) $totals->total_debit;
        $totalCredit = (int) $totals->total_credit;

        return match ($account->nature) {
            AccountNature::Asset, AccountNature::Expense => $totalDebit - $totalCredit,
            AccountNature::Liability, AccountNature::Revenue => $totalCredit - $totalDebit,
        };
    }
}
