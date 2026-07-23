<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Services\Exceptions\NonPositiveAmountException;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADR-0003 §15, §17 : un posting ne peut jamais avoir un montant nul ou
 * négatif. Vérifié au niveau du service et, indépendamment, par la
 * contrainte CHECK `postings_amount_positive_check`.
 */
class NonPositiveAmountTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_a_zero_amount_posting_is_refused_by_the_service(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('zero_amount');

        $intent = $this->makeIntent(postings: [
            new PostingLine($debitAccount->id, PostingDirection::Debit, 0, 'XOF', 'Débit nul'),
            new PostingLine($creditAccount->id, PostingDirection::Credit, 0, 'XOF', 'Crédit nul'),
        ]);

        $this->expectException(NonPositiveAmountException::class);

        $this->poster()->post($intent);
    }

    public function test_a_negative_amount_posting_is_refused_by_the_service(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('negative_amount');

        $intent = $this->makeIntent(postings: [
            new PostingLine($debitAccount->id, PostingDirection::Debit, -500, 'XOF', 'Débit négatif'),
            new PostingLine($creditAccount->id, PostingDirection::Credit, 500, 'XOF', 'Crédit'),
        ]);

        $this->expectException(NonPositiveAmountException::class);

        $this->poster()->post($intent);
    }

    /**
     * Contournement direct du service : la contrainte CHECK
     * `postings_amount_positive_check` refuse un montant négatif dès
     * l'INSERT, sans attendre la validation de la transaction SQL.
     */
    public function test_a_negative_amount_posting_is_refused_by_postgres_even_bypassing_the_service(): void
    {
        [$debitAccount] = $this->makeAccountPair('negative_amount_db');
        $transactionId = (string) Str::uuid7();

        DB::table('ledger.ledger_transactions')->insert([
            'id' => $transactionId,
            'type' => 'raw_test_movement',
            'state' => 'posted',
            'business_date' => now()->toDateString(),
            'accounting_date' => now()->toDateString(),
            'source_module' => 'wallet_test',
            'source_reference' => (string) Str::uuid(),
            'idempotency_scope' => 'raw_test',
            'idempotency_key' => (string) Str::uuid(),
            'idempotency_fingerprint' => hash('sha256', (string) Str::uuid()),
            'correlation_id' => (string) Str::uuid(),
            'authored_by' => 'wallet.ledger.test_suite',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('ledger.postings')->insert([
            'id' => (string) Str::uuid7(),
            'ledger_transaction_id' => $transactionId,
            'account_id' => $debitAccount->id,
            'direction' => 'debit',
            'amount' => -100,
            'currency' => 'XOF',
            'dimensions' => '{}',
            'label' => 'Débit négatif brut',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
