<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal append-only des décisions d'autorisation (P003-B1 §17). Le moteur
 * enregistre les refus comme les autorisations. Aucun secret, OTP, document
 * KYC, donnée médicale, profil publicitaire ou payload métier complet n'y
 * figure.
 *
 * Historique figé localement (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const OPERATION_VALUES = ['read', 'write', 'export'];

    /**
     * @var list<string>
     */
    private const DECISION_VALUES = [
        'allowed', 'denied', 'step_up_required', 'approval_required', 'allowed_masked', 'allowed_read_only',
    ];

    public function up(): void
    {
        Schema::create('governance.authorization_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('correlation_id');
            $table->foreignUuid('person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();
            $table->foreignUuid('membership_id')
                ->nullable()
                ->constrained('identity.memberships')
                ->restrictOnDelete();
            $table->foreignUuid('organization_id')
                ->nullable()
                ->constrained('identity.organizations')
                ->restrictOnDelete();
            $table->string('capability_key');
            $table->integer('capability_version')->nullable();
            $table->string('purpose_key')->nullable();
            $table->string('resource_type')->nullable();
            $table->string('resource_id', 255)->nullable();
            $table->string('operation');
            $table->string('decision');
            $table->string('reason_code');
            $table->string('policy_key')->nullable();
            $table->integer('policy_version')->nullable();
            $table->jsonb('obligations')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->index('correlation_id');
            $table->index('person_account_link_id');
            $table->index('capability_key');
            $table->index('decision');
        });

        $operation = implode(',', array_map(fn (string $value): string => "'{$value}'", self::OPERATION_VALUES));
        $decision = implode(',', array_map(fn (string $value): string => "'{$value}'", self::DECISION_VALUES));

        DB::statement(
            "ALTER TABLE governance.authorization_decisions ADD CONSTRAINT authorization_decisions_operation_check CHECK (operation IN ({$operation}))"
        );

        DB::statement(
            "ALTER TABLE governance.authorization_decisions ADD CONSTRAINT authorization_decisions_decision_check CHECK (decision IN ({$decision}))"
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_authorization_decisions_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'governance: le journal authorization_decisions est append-only (P003-B1 §17)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER authorization_decisions_prevent_update BEFORE UPDATE ON governance.authorization_decisions '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_authorization_decisions_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER authorization_decisions_prevent_delete BEFORE DELETE ON governance.authorization_decisions '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_authorization_decisions_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS authorization_decisions_prevent_delete ON governance.authorization_decisions');
        DB::statement('DROP TRIGGER IF EXISTS authorization_decisions_prevent_update ON governance.authorization_decisions');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_authorization_decisions_mutation()');
        Schema::dropIfExists('governance.authorization_decisions');
    }
};
