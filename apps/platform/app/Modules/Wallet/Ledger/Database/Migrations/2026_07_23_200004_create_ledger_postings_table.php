<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Écriture élémentaire d'une transaction comptable (ADR-0003 §15,
 * architecture/05). Un posting a toujours un montant entier strictement
 * positif et un sens explicite : jamais de montant signé.
 *
 * Comme `ledger.ledger_transactions`, une ligne comptabilisée n'est plus
 * jamais modifiée ni supprimée (voir la migration
 * `add_ledger_immutability...`).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const DIRECTION_VALUES = ['debit', 'credit'];

    public function up(): void
    {
        Schema::create('ledger.postings', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('ledger_transaction_id')
                ->constrained('ledger.ledger_transactions')
                ->restrictOnDelete();
            $table->foreignUuid('account_id')
                ->constrained('ledger.accounts')
                ->restrictOnDelete();

            $table->string('direction');
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->jsonb('dimensions');
            $table->string('label');

            $table->timestamps();

            $table->index('ledger_transaction_id');
            $table->index('account_id');
        });

        $direction = $this->quotedList(self::DIRECTION_VALUES);

        DB::statement(
            "ALTER TABLE ledger.postings ADD CONSTRAINT postings_direction_check CHECK (direction IN ({$direction}))"
        );

        DB::statement(
            'ALTER TABLE ledger.postings ADD CONSTRAINT postings_amount_positive_check CHECK (amount > 0)'
        );

        DB::statement(
            "ALTER TABLE ledger.postings ADD CONSTRAINT postings_currency_format_check CHECK (currency ~ '^[A-Z]{3}$')"
        );

        DB::statement(
            'ALTER TABLE ledger.postings ADD CONSTRAINT postings_dimensions_is_object_check '
            ."CHECK (jsonb_typeof(dimensions) = 'object')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger.postings');
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(',', array_map(fn (string $value): string => "'{$value}'", $values));
    }
};
