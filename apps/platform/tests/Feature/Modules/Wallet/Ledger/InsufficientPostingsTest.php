<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Services\Exceptions\InsufficientPostingsException;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * architecture/05 "Règles structurelles" : une transaction comptabilisée
 * contient au moins deux postings.
 */
class InsufficientPostingsTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_a_transaction_with_a_single_posting_is_refused_by_the_service(): void
    {
        [$debitAccount] = $this->makeAccountPair('single_posting');

        $intent = $this->makeIntent(postings: [
            new PostingLine($debitAccount->id, PostingDirection::Debit, 1_000, 'XOF', 'Débit isolé'),
        ]);

        $this->expectException(InsufficientPostingsException::class);

        $this->poster()->post($intent);
    }

    public function test_a_transaction_with_no_postings_is_refused_by_the_service(): void
    {
        $intent = $this->makeIntent(postings: []);

        $this->expectException(InsufficientPostingsException::class);

        $this->poster()->post($intent);
    }

    /**
     * Contournement direct du service : un seul posting inséré pour une
     * transaction. Le déclencheur différé `postings_enforce_balance` refuse
     * explicitement ce cas (en plus de refuser l'équilibre, un montant
     * strictement positif isolé ne peut de toute façon jamais s'équilibrer).
     */
    public function test_a_single_posting_is_refused_by_postgres_even_bypassing_the_service(): void
    {
        [$debitAccount] = $this->makeAccountPair('single_posting_db');
        $transactionId = (string) Str::uuid7();

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($transactionId, $debitAccount): void {
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

            DB::table('ledger.postings')->insert([
                'id' => (string) Str::uuid7(),
                'ledger_transaction_id' => $transactionId,
                'account_id' => $debitAccount->id,
                'direction' => 'debit',
                'amount' => 1_000,
                'currency' => 'XOF',
                'dimensions' => '{}',
                'label' => 'Débit isolé brut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }
}
