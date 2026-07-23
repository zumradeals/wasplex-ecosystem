<?php

use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transaction comptable (ADR-0003 §15). Une fois comptabilisée, une ligne de
 * cette table n'est plus jamais modifiée ni supprimée (ADR-0003 §11) : voir
 * les déclencheurs posés par la migration `add_ledger_immutability...`.
 *
 * La clé d'idempotence (`idempotency_scope`, `idempotency_key`) est unique
 * dans son périmètre (ADR-0003 §10) : rejouer la même intention avec la même
 * empreinte (`idempotency_fingerprint`) ne crée jamais un second effet
 * comptable — voir {@see LedgerPoster}.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['posted'];

    public function up(): void
    {
        Schema::create('ledger.ledger_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('type');
            $table->string('state');

            $table->date('business_date');
            $table->date('accounting_date');

            $table->string('source_module');
            $table->string('source_reference');

            $table->string('configuration_key')->nullable();
            $table->unsignedInteger('configuration_version')->nullable();

            $table->string('idempotency_scope');
            $table->string('idempotency_key');
            $table->char('idempotency_fingerprint', 64);

            $table->uuid('correlation_id');
            $table->string('authored_by');
            $table->string('evidence_reference')->nullable();

            // Auto-référence posée hors du Blueprint (voir plus bas) : le
            // grammaire Postgres de Laravel compile les contraintes de clé
            // étrangère avant l'ajout de la clé primaire, ce qui échouerait
            // ici puisque la table référencée est elle-même.
            $table->uuid('reverses_transaction_id')->nullable();
            $table->string('reversal_reason')->nullable();

            $table->timestamps();

            $table->unique(['idempotency_scope', 'idempotency_key'], 'ledger_transactions_idempotency_unique');
            $table->index('source_module');
            $table->index('business_date');
            $table->index('accounting_date');
            $table->index('correlation_id');
        });

        DB::statement(
            'ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_reverses_transaction_id_foreign '
            .'FOREIGN KEY (reverses_transaction_id) REFERENCES ledger.ledger_transactions (id) ON DELETE RESTRICT'
        );

        $state = $this->quotedList(self::STATE_VALUES);

        DB::statement(
            "ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            "ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_type_format_check CHECK (type ~ '^[a-z][a-z0-9_]*$')"
        );

        DB::statement(
            'ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_accounting_after_business_check '
            .'CHECK (accounting_date >= business_date)'
        );

        DB::statement(
            "ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_fingerprint_format_check CHECK (idempotency_fingerprint ~ '^[0-9a-f]{64}$')"
        );

        // Une contre-écriture référence toujours son original et un motif ;
        // une transaction ordinaire ne référence jamais de motif de reprise
        // (ADR-0003 §11).
        DB::statement(
            'ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_reversal_reason_coherence_check '
            .'CHECK ((reverses_transaction_id IS NULL) = (reversal_reason IS NULL))'
        );

        DB::statement(
            'ALTER TABLE ledger.ledger_transactions ADD CONSTRAINT ledger_transactions_reversal_not_self_check '
            .'CHECK (reverses_transaction_id IS NULL OR reverses_transaction_id <> id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger.ledger_transactions');
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(',', array_map(fn (string $value): string => "'{$value}'", $values));
    }
};
