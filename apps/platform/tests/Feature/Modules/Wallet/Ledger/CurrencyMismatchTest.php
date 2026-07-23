<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Services\Exceptions\CurrencyMismatchException;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ADR-0003 §5, §17 : une transaction ne s'équilibre que dans une devise.
 * Vérifié au niveau du service et, indépendamment, par le déclencheur
 * `postings_enforce_balance`.
 */
class CurrencyMismatchTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_a_transaction_mixing_two_currencies_is_refused_by_the_service(): void
    {
        $xofAccount = $this->makeAccount('coverage.mix_xof', AccountNature::Asset, AccountPurpose::Coverage, 'XOF');
        $eurAccount = $this->makeAccount('coverage.mix_eur', AccountNature::Liability, AccountPurpose::UserRights, 'EUR');

        $intent = $this->makeIntent(postings: [
            new PostingLine($xofAccount->id, PostingDirection::Debit, 1_000, 'XOF', 'Débit XOF'),
            new PostingLine($eurAccount->id, PostingDirection::Credit, 1_000, 'EUR', 'Crédit EUR'),
        ]);

        $this->expectException(CurrencyMismatchException::class);

        $this->poster()->post($intent);
    }

    /**
     * Contournement direct du service : deux postings de devises
     * différentes insérés en base pour la même transaction. Le compte
     * accepte lui-même une seule devise (`accounts_currency_format_check` +
     * `enforce_posting_account_rules`), donc chaque posting doit référencer
     * un compte de la devise correspondante pour passer ce premier filtre —
     * c'est bien le mélange *entre* postings d'une même transaction que ce
     * test vise, pas la cohérence compte/posting (déjà couverte ailleurs).
     */
    public function test_currency_mixing_is_refused_by_postgres_even_bypassing_the_service(): void
    {
        $xofAccount = $this->makeAccount('coverage.mix_xof_db', AccountNature::Asset, AccountPurpose::Coverage, 'XOF');
        $eurAccount = $this->makeAccount('coverage.mix_eur_db', AccountNature::Liability, AccountPurpose::UserRights, 'EUR');
        $transactionId = (string) Str::uuid7();

        $this->expectException(QueryException::class);

        DB::transaction(function () use ($transactionId, $xofAccount, $eurAccount): void {
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
                'account_id' => $xofAccount->id,
                'direction' => 'debit',
                'amount' => 1_000,
                'currency' => 'XOF',
                'dimensions' => '{}',
                'label' => 'Débit XOF brut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ledger.postings')->insert([
                'id' => (string) Str::uuid7(),
                'ledger_transaction_id' => $transactionId,
                'account_id' => $eurAccount->id,
                'direction' => 'credit',
                'amount' => 1_000,
                'currency' => 'EUR',
                'dimensions' => '{}',
                'label' => 'Crédit EUR brut',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        });
    }
}
