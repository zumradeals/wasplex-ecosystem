<?php

namespace App\Modules\Wallet\Balance\Services;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Database\QueryException;

/**
 * Provisionnement paresseux du compte Ledger `user_rights` d'une personne
 * précise (ADR-0003 §4, §6 ; ecosystem/wallet/01 §3). Ferme la dette
 * TD-0004-F : jusqu'ici, la part utilisateur d'un événement qualifié
 * créditait un unique compte `user_rights` mutualisé par devise
 * (`App\Modules\Advertising\Services\SharedLedgerAccounts::userRights()`),
 * reconstructible seulement par agrégation de postings sur une dimension.
 * Un compte réellement séparé par bénéficiaire permet à Wallet d'exposer un
 * solde individuel sans reconstruction indirecte.
 *
 * Seul l'état « disponible » est provisionné ici : aucune transition réelle
 * ne produit encore de WP provisoire ou réservé pour un utilisateur (voir
 * TD-0005) — construire ces comptes maintenant créerait des comptes morts,
 * jamais crédités ni débités par aucun flux existant.
 */
class PersonLedgerAccounts
{
    public function available(string $personId, string $currency): Account
    {
        $suffix = str_replace('-', '', $personId);
        $code = "user_rights.person.{$suffix}.".strtolower($currency).'.available';

        $existing = Account::query()->where('code', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        try {
            return Account::create([
                'code' => $code,
                'nature' => AccountNature::Liability,
                'purpose' => AccountPurpose::UserRights,
                'legal_entity' => 'wasplex',
                'country_code' => 'CI',
                'currency' => $currency,
                'module' => 'wallet',
                'compartment' => $personId,
                'status' => AccountStatus::Active,
                'movement_restrictions' => [],
            ]);
        } catch (QueryException $exception) {
            // Provisionnement concurrent du même compte individuel (première
            // rémunération de cette personne dans cette devise) : le gagnant
            // de la course sur la contrainte unique du code fait autorité
            // (même garde que SharedLedgerAccounts::getOrCreate()).
            $existing = Account::query()->where('code', $code)->first();
            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }
}
