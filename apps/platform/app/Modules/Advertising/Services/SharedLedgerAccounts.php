<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Database\QueryException;

/**
 * Comptes Ledger partagés, mutualisés par devise plutôt que dupliqués par
 * campagne (ADR-0003 §4 : les actifs de couverture et les revenus acquis
 * de Wasplex sont des positions globales, jamais un compartiment par
 * campagne). Utilise `Account` tel quel (P005-A §3.D) : aucune nouvelle
 * logique de compte, seulement leur provisionnement paresseux.
 */
class SharedLedgerAccounts
{
    public function coverage(string $currency): Account
    {
        return $this->getOrCreate('coverage.advertising', AccountNature::Asset, AccountPurpose::Coverage, $currency);
    }

    public function userRights(string $currency): Account
    {
        return $this->getOrCreate('user_rights.advertising', AccountNature::Liability, AccountPurpose::UserRights, $currency);
    }

    public function wasplexRevenue(string $currency): Account
    {
        return $this->getOrCreate('wasplex_own_resources.advertising', AccountNature::Revenue, AccountPurpose::WasplexOwnResources, $currency);
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
                'module' => 'advertising',
                'compartment' => null,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);
        } catch (QueryException $exception) {
            // Provisionnement concurrent du même compte partagé (rare,
            // première utilisation d'une devise) : le gagnant de la course
            // sur la contrainte unique du code fait autorité.
            $existing = Account::query()->where('code', $code)->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }
}
