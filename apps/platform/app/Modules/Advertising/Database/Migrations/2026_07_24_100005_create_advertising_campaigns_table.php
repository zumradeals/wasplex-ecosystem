<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Identité stable d'une campagne et son état global (ADR-0010 §3
 * « Campaign »). Le budget n'est jamais une colonne sur cette table — voir
 * `CampaignBudgetProjection` — mais les trois comptes `ledger.accounts`
 * dédiés (disponible/réservé/consommé, compartiment « campagne
 * annonceur », ADR-0003 §4) sont référencés ici pour que la frontière avec
 * le Ledger soit explicite et stable (ADR-0010 §3 « CampaignBudget »).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['active', 'suspended', 'closed'];

    public function up(): void
    {
        Schema::create('advertising.campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('advertiser_profile_id')
                ->constrained('advertising.advertiser_profiles')
                ->restrictOnDelete();

            $table->string('code')->unique();
            $table->char('currency', 3);
            $table->string('state');

            $table->foreignUuid('available_account_id')
                ->constrained('ledger.accounts')
                ->restrictOnDelete();
            $table->foreignUuid('reserved_account_id')
                ->constrained('ledger.accounts')
                ->restrictOnDelete();
            $table->foreignUuid('consumed_account_id')
                ->constrained('ledger.accounts')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('advertiser_profile_id');
            $table->index('state');
        });

        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE advertising.campaigns ADD CONSTRAINT campaigns_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            "ALTER TABLE advertising.campaigns ADD CONSTRAINT campaigns_currency_format_check CHECK (currency ~ '^[A-Z]{3}$')"
        );

        DB::statement(
            'ALTER TABLE advertising.campaigns ADD CONSTRAINT campaigns_three_distinct_budget_accounts_check '
            .'CHECK (available_account_id <> reserved_account_id AND available_account_id <> consumed_account_id AND reserved_account_id <> consumed_account_id)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.campaigns');
    }
};
