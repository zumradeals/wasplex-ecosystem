<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal append-only des événements de grant (P003-B1 §13, AMD-0012 §16).
 * Une correction produit un nouvel événement ; elle ne réécrit jamais l'ancien.
 *
 * Historique figé localement (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const EVENT_TYPE_VALUES = ['proposed', 'activated', 'suspended', 'revoked', 'expired'];

    public function up(): void
    {
        Schema::create('governance.grant_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('grant_id')->constrained('governance.grants')->restrictOnDelete();
            $table->foreignUuid('actor_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();
            $table->foreignUuid('organization_id')
                ->nullable()
                ->constrained('identity.organizations')
                ->restrictOnDelete();
            $table->string('event_type');
            $table->string('reason', 500)->nullable();
            $table->foreignUuid('policy_version_id')->constrained('governance.policy_versions')->restrictOnDelete();
            $table->uuid('correlation_id');
            $table->timestampTz('occurred_at');
            $table->timestampTz('created_at');

            $table->index('grant_id');
            $table->index('correlation_id');
        });

        $eventType = implode(',', array_map(fn (string $value): string => "'{$value}'", self::EVENT_TYPE_VALUES));

        DB::statement(
            "ALTER TABLE governance.grant_events ADD CONSTRAINT grant_events_event_type_check CHECK (event_type IN ({$eventType}))"
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_grant_events_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'governance: le journal grant_events est append-only (P003-B1 §13)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER grant_events_prevent_update BEFORE UPDATE ON governance.grant_events '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_grant_events_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER grant_events_prevent_delete BEFORE DELETE ON governance.grant_events '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_grant_events_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS grant_events_prevent_delete ON governance.grant_events');
        DB::statement('DROP TRIGGER IF EXISTS grant_events_prevent_update ON governance.grant_events');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_grant_events_mutation()');
        Schema::dropIfExists('governance.grant_events');
    }
};
