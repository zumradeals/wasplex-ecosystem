<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\Account;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class LedgerTestCase extends TestCase
{
    protected function makeAccount(
        string $code,
        AccountNature $nature,
        AccountPurpose $purpose = AccountPurpose::UserRights,
        string $currency = 'XOF',
        AccountStatus $status = AccountStatus::Active,
        array $movementRestrictions = [],
    ): Account {
        return Account::create([
            'code' => $code,
            'nature' => $nature,
            'purpose' => $purpose,
            'legal_entity' => 'wasplex_ci',
            'country_code' => 'CI',
            'currency' => $currency,
            'module' => 'wallet',
            'compartment' => null,
            'status' => $status,
            'movement_restrictions' => $movementRestrictions,
        ]);
    }

    /**
     * Deux comptes d'actif et de passif, en équilibre naturel : débiter
     * l'un et créditer l'autre du même montant forme une transaction valide
     * pour les tests qui n'ont pas besoin d'un plan de comptes spécifique.
     *
     * @return array{0: Account, 1: Account}
     */
    protected function makeAccountPair(string $suffix, string $currency = 'XOF'): array
    {
        $debitAccount = $this->makeAccount("coverage.test_{$suffix}", AccountNature::Asset, AccountPurpose::Coverage, $currency);
        $creditAccount = $this->makeAccount("user_rights.test_{$suffix}", AccountNature::Liability, AccountPurpose::UserRights, $currency);

        return [$debitAccount, $creditAccount];
    }

    /**
     * @param  list<PostingLine>|null  $postings
     */
    protected function makeIntent(
        ?array $postings = null,
        string $type = 'test_movement',
        string $idempotencyScope = 'test_scope',
        ?string $idempotencyKey = null,
        ?string $sourceReference = null,
        ?string $correlationId = null,
        ?CarbonInterface $businessDate = null,
        ?CarbonInterface $accountingDate = null,
    ): TransactionIntent {
        $idempotencyKey ??= (string) Str::uuid();

        return new TransactionIntent(
            type: $type,
            businessDate: $businessDate ?? now(),
            accountingDate: $accountingDate ?? now(),
            sourceModule: 'wallet_test',
            // Dérivée de la clé d'idempotence par défaut : deux appels
            // partageant la même clé partagent aussi, par défaut, la même
            // référence source, comme le ferait un même événement métier
            // rejoué. Un test qui veut une référence source différente pour
            // une même clé (afin d'obtenir un contenu réellement différent)
            // la fournit explicitement.
            sourceReference: $sourceReference ?? 'source-for-'.$idempotencyKey,
            idempotencyScope: $idempotencyScope,
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId ?? (string) Str::uuid(),
            authoredBy: 'wallet.ledger.test_suite',
            postings: $postings ?? [],
        );
    }

    protected function debitCreditIntent(
        Account $debitAccount,
        Account $creditAccount,
        int $amount,
        string $currency = 'XOF',
        string $idempotencyScope = 'test_scope',
        ?string $idempotencyKey = null,
    ): TransactionIntent {
        return $this->makeIntent(
            postings: [
                new PostingLine($debitAccount->id, PostingDirection::Debit, $amount, $currency, 'Débit de test'),
                new PostingLine($creditAccount->id, PostingDirection::Credit, $amount, $currency, 'Crédit de test'),
            ],
            idempotencyScope: $idempotencyScope,
            idempotencyKey: $idempotencyKey,
        );
    }

    protected function poster(): LedgerPoster
    {
        return app(LedgerPoster::class);
    }
}
