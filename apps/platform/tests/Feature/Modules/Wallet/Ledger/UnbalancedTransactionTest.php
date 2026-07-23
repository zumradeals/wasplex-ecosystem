<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Services\Exceptions\UnbalancedTransactionException;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADR-0003 §17 : impossibilité de comptabiliser une transaction
 * déséquilibrée. Vérifié à deux niveaux indépendants (comme
 * Governance/Authorization) : le service refuse avant toute écriture, et la
 * base refuse même un contournement direct du service.
 */
class UnbalancedTransactionTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_an_unbalanced_transaction_is_refused_by_the_service(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('unbalanced_service');

        $intent = $this->makeIntent(postings: [
            new PostingLine($debitAccount->id, PostingDirection::Debit, 1_000, 'XOF', 'Débit'),
            new PostingLine($creditAccount->id, PostingDirection::Credit, 900, 'XOF', 'Crédit'),
        ]);

        try {
            $this->poster()->post($intent);
            $this->fail('UnbalancedTransactionException attendue.');
        } catch (UnbalancedTransactionException) {
            // Attendu : aucune écriture ne doit avoir été tentée.
        }

        $this->assertDatabaseCount('ledger.ledger_transactions', 0);
        $this->assertDatabaseCount('ledger.postings', 0);
    }

    /**
     * Contournement direct du service : insertion SQL brute des mêmes
     * lignes déséquilibrées. Le déclencheur différé
     * `postings_enforce_balance` doit refuser à la validation de la
     * transaction SQL, même sans passer par LedgerPoster.
     */
    public function test_an_unbalanced_transaction_is_refused_by_postgres_even_bypassing_the_service(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('unbalanced_db');
        $transactionId = (string) Str::uuid7();

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($transactionId, $debitAccount, $creditAccount): void {
            DB::table('ledger.ledger_transactions')->insert($this->rawTransactionRow($transactionId));

            DB::table('ledger.postings')->insert([
                'id' => (string) Str::uuid7(),
                'ledger_transaction_id' => $transactionId,
                'account_id' => $debitAccount->id,
                'direction' => 'debit',
                'amount' => 1_000,
                'currency' => 'XOF',
                'dimensions' => '{}',
                'label' => 'Débit brut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ledger.postings')->insert([
                'id' => (string) Str::uuid7(),
                'ledger_transaction_id' => $transactionId,
                'account_id' => $creditAccount->id,
                'direction' => 'credit',
                'amount' => 900,
                'currency' => 'XOF',
                'dimensions' => '{}',
                'label' => 'Crédit brut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sous RefreshDatabase, ce DB::transaction() imbriqué n'est
            // qu'une savepoint à l'intérieur de la transaction englobante du
            // test, jamais réellement validée : sans cette ligne, le
            // déclencheur de contrainte différé ne s'exécuterait jamais
            // pendant le test. On force ici son évaluation immédiate, comme
            // le ferait un vrai COMMIT en production.
            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function rawTransactionRow(string $id): array
    {
        return [
            'id' => $id,
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
        ];
    }
}
