<?php

use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plan de comptes du Wallet (ADR-0003 §4, §15). Un compte n'accepte qu'une
 * seule devise (ADR-0003 §1) et ne stocke jamais de solde d'autorité : le
 * solde est une projection reconstruite depuis `ledger.postings`
 * (voir {@see AccountBalanceProjection}).
 *
 * Historique figé localement, sur le modèle de
 * `governance.capability_definitions` (P003-A.2 §3) : les valeurs fermées
 * ci-dessous ne doivent jamais être remplacées par une référence à un enum
 * applicatif dans une contrainte SQL.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const NATURE_VALUES = ['asset', 'liability', 'revenue', 'expense'];

    /**
     * @var list<string>
     */
    private const PURPOSE_VALUES = [
        'coverage', 'advertiser_campaign', 'user_rights', 'wasplex_own_resources',
        'social_fund', 'cards_pool', 'tax_and_fees', 'transit_payment', 'clearing',
    ];

    /**
     * @var list<string>
     */
    private const STATUS_VALUES = ['active', 'frozen', 'closed'];

    public function up(): void
    {
        Schema::create('ledger.accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('nature');
            $table->string('purpose');
            $table->string('legal_entity');
            $table->string('country_code', 2);
            $table->char('currency', 3);
            $table->string('module');
            $table->string('compartment')->nullable();
            $table->string('status');
            $table->jsonb('movement_restrictions');
            $table->timestamps();

            $table->index('purpose');
            $table->index('currency');
            $table->index('status');
        });

        $nature = $this->quotedList(self::NATURE_VALUES);
        $purpose = $this->quotedList(self::PURPOSE_VALUES);
        $status = $this->quotedList(self::STATUS_VALUES);

        DB::statement(
            "ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_nature_check CHECK (nature IN ({$nature}))"
        );

        DB::statement(
            "ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_purpose_check CHECK (purpose IN ({$purpose}))"
        );

        DB::statement(
            "ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_status_check CHECK (status IN ({$status}))"
        );

        // Format ISO 4217 (trois lettres majuscules) ; ADR-0003 §5 impose une
        // devise entière et explicite, jamais un code libre.
        DB::statement(
            "ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_currency_format_check CHECK (currency ~ '^[A-Z]{3}$')"
        );

        DB::statement(
            "ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_country_code_format_check CHECK (country_code ~ '^[A-Z]{2}$')"
        );

        DB::statement(
            "ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_code_format_check CHECK (code ~ '^[a-z][a-z0-9_.]*$')"
        );

        DB::statement(
            'ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_movement_restrictions_is_object_check '
            ."CHECK (jsonb_typeof(movement_restrictions) = 'object')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger.accounts');
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(',', array_map(fn (string $value): string => "'{$value}'", $values));
    }
};
