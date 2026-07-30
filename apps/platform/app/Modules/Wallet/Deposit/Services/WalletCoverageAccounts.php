<?php

namespace App\Modules\Wallet\Deposit\Services;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Database\QueryException;

/**
 * Comptes Ledger partagés du dépôt Wallet, mutualisés par devise
 * (ecosystem/wallet/05 §3) — même raisonnement et même gabarit que
 * `App\Modules\Advertising\Services\SharedLedgerAccounts` : les actifs de
 * couverture et les charges de frais sont des positions globales, jamais
 * un compartiment par dépôt.
 */
class WalletCoverageAccounts
{
    public function coverage(string $currency): Account
    {
        return $this->getOrCreate('coverage.wallet', AccountNature::Asset, AccountPurpose::Coverage, $currency);
    }

    public function feesExpense(string $currency): Account
    {
        return $this->getOrCreate('tax_and_fees.wallet', AccountNature::Expense, AccountPurpose::TaxAndFees, $currency);
    }

    private function getOrCreate(string $codePrefix, AccountNature $nature, AccountPurpose $purpose, string $currency): Account
    {
        $code = $codePrefix.'.'.strtolower($currency);

        $existing = Account::query()->where('code', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return Account::create([
                'code' => $code,
                'nature' => $nature,
                'purpose' => $purpose,
                'legal_entity' => 'wasplex',
                'country_code' => 'CI',
                'currency' => $currency,
                'module' => 'wallet',
                'compartment' => null,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);
        } catch (QueryException $exception) {
            $existing = Account::query()->where('code', $code)->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }
}
