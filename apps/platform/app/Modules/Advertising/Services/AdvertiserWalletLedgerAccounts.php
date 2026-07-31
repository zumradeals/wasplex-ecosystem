<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Wallet\Balance\Services\PersonLedgerAccounts;
use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Database\QueryException;

/**
 * Provisionnement paresseux du solde annonceur mutualisé (instruction
 * explicite du fondateur, 2026-07-31) — un compte « disponible » par
 * (annonceur, devise), jamais par campagne (à la différence de
 * {@see CampaignService}, qui provisionne trois comptes par campagne). Même
 * gabarit que {@see PersonLedgerAccounts}
 * : un dépôt reçu avant même qu'une campagne n'existe doit pouvoir être
 * couvert quelque part.
 */
class AdvertiserWalletLedgerAccounts
{
    public function available(AdvertiserProfile $advertiser, string $currency): Account
    {
        $suffix = str_replace('-', '', $advertiser->id);
        $code = "advertiser_wallet.{$suffix}.".strtolower($currency).'.available';

        $existing = Account::query()->where('code', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return Account::create([
                'code' => $code,
                'nature' => AccountNature::Liability,
                'purpose' => AccountPurpose::AdvertiserWallet,
                'legal_entity' => 'wasplex',
                'country_code' => 'CI',
                'currency' => $currency,
                'module' => 'advertising',
                'compartment' => $advertiser->id,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);
        } catch (QueryException $exception) {
            // Provisionnement concurrent du même compte (premier dépôt de cet
            // annonceur dans cette devise) : le gagnant de la course sur la
            // contrainte unique du code fait autorité (même garde que
            // SharedLedgerAccounts::getOrCreate()).
            $existing = Account::query()->where('code', $code)->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }
}
