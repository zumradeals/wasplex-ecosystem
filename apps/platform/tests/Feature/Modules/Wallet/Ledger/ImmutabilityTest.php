<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0003 §11 : une transaction comptabilisée ne peut être ni modifiée ni
 * supprimée. Aucune exception de cycle de vie ici, contrairement aux grants
 * de Governance/Authorization : toute UPDATE ou DELETE SQL directe échoue,
 * quel que soit le champ visé.
 */
class ImmutabilityTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_direct_sql_update_on_a_posted_transaction_fails(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('immut_tx_update');
        $transaction = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));

        $this->expectException(QueryException::class);

        DB::table('ledger.ledger_transactions')->where('id', $transaction->id)->update(['source_reference' => 'altered']);
    }

    public function test_direct_sql_delete_on_a_posted_transaction_fails(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('immut_tx_delete');
        $transaction = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));

        $this->expectException(QueryException::class);

        DB::table('ledger.ledger_transactions')->where('id', $transaction->id)->delete();
    }

    public function test_direct_sql_update_on_a_posting_fails(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('immut_posting_update');
        $transaction = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));
        $posting = $transaction->postings->first();

        $this->expectException(QueryException::class);

        DB::table('ledger.postings')->where('id', $posting->id)->update(['amount' => 999_999]);
    }

    public function test_direct_sql_delete_on_a_posting_fails(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('immut_posting_delete');
        $transaction = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));
        $posting = $transaction->postings->first();

        $this->expectException(QueryException::class);

        DB::table('ledger.postings')->where('id', $posting->id)->delete();
    }

    /**
     * Même après une contre-écriture, l'original n'est jamais réellement
     * modifié en base — seule une nouvelle ligne existe (ADR-0003 §11).
     */
    public function test_a_transaction_cannot_be_updated_even_after_being_reversed(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('immut_after_reversal');
        $original = $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));

        $reversalIntent = $this->debitCreditIntent($creditAccount, $debitAccount, 1_000, idempotencyKey: 'reversal-of-'.$original->id);
        $this->poster()->reverse($original, $reversalIntent, 'Erreur de saisie constatée');

        $this->expectException(QueryException::class);

        DB::table('ledger.ledger_transactions')->where('id', $original->id)->update(['reversal_reason' => 'tentative de réécriture']);
    }
}
