<?php

namespace App\Modules\Wallet\Balance\Projections;

use App\Modules\Wallet\Balance\Services\PersonLedgerAccounts;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Models\Account;
use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;

/**
 * Solde WP d'une personne, par devise (ecosystem/wallet/01 §3, §7). Comme
 * {@see AccountBalanceProjection} dont elle dépend, cette projection ne lit
 * jamais un champ de solde stocké : elle reconstruit depuis les comptes
 * `user_rights` individuels provisionnés par {@see PersonLedgerAccounts}.
 *
 * Ne lit que l'état disponible : aucun compte provisoire ni réservé n'existe
 * encore par personne (TD-0005). Une personne sans aucun compte provisionné
 * (aucune rémunération reçue) obtient une liste vide, jamais une erreur.
 */
class PersonBalanceProjection
{
    public function __construct(
        private readonly AccountBalanceProjection $accountBalance,
    ) {}

    /**
     * `provisional`/`reserved` sont typés à leur unique valeur actuelle
     * (`0`, littéralement) plutôt qu'`int` : aucun état provisoire ni
     * réservé n'existe encore par personne (TD-0005-A) — élargir ce type
     * reviendrait à promettre une variation qui n'existe pas dans ce lot.
     *
     * @return list<array{currency: string, available: int, provisional: 0, reserved: 0}>
     */
    public function forPerson(string $personId): array
    {
        $accounts = Account::query()
            ->where('purpose', AccountPurpose::UserRights)
            ->where('compartment', $personId)
            ->orderBy('currency')
            ->get();

        return array_values($accounts
            ->map(fn (Account $account): array => [
                'currency' => $account->currency,
                'available' => $this->accountBalance->currentBalance($account),
                'provisional' => 0,
                'reserved' => 0,
            ])
            ->all());
    }
}
