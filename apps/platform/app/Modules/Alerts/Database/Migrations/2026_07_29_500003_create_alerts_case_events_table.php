<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerts.case_events` (P008-A) : journal append-only de la machine
 * d'états d'un dossier — même discipline que `configuration.approvals`,
 * `governance.grant_events` : jamais modifié ni supprimé après création,
 * une correction ajoute un événement compensatoire (AMD-0007 §5 : « chaque
 * statut affiché correspond littéralement à une preuve réelle »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts.case_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('case_id')
                ->constrained('alerts.cases')
                ->restrictOnDelete();

            $table->string('event_type');
            $table->string('from_state')->nullable();
            $table->string('to_state')->nullable();

            $table->foreignUuid('actor_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->foreignUuid('actor_organization_id')
                ->nullable()
                ->constrained('identity.organizations')
                ->restrictOnDelete();

            $table->string('channel')->nullable();
            $table->string('correlation_id');
            $table->string('idempotency_key')->nullable();
            $table->jsonb('metadata');

            $table->timestampTz('occurred_at');

            $table->timestamps();

            $table->unique(['case_id', 'idempotency_key']);
            $table->index(['case_id', 'occurred_at']);
        });

        DB::statement(
            'ALTER TABLE alerts.case_events ADD CONSTRAINT case_events_metadata_is_object_check '
            ."CHECK (jsonb_typeof(metadata) = 'object')"
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION alerts.prevent_case_events_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'alerts: un case_event ne peut jamais être modifié ni supprimé (AMD-0007 §5) ; ajoutez un événement compensatoire';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER case_events_prevent_update BEFORE UPDATE ON alerts.case_events '
            .'FOR EACH ROW EXECUTE FUNCTION alerts.prevent_case_events_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER case_events_prevent_deletion BEFORE DELETE ON alerts.case_events '
            .'FOR EACH ROW EXECUTE FUNCTION alerts.prevent_case_events_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS case_events_prevent_deletion ON alerts.case_events');
        DB::statement('DROP TRIGGER IF EXISTS case_events_prevent_update ON alerts.case_events');
        DB::statement('DROP FUNCTION IF EXISTS alerts.prevent_case_events_mutation()');
        Schema::dropIfExists('alerts.case_events');
    }
};
