<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activation (ADR-0002 §8) : preuve de mise en effet d'une `ValueVersion`
 * approuvée. Table distincte de `value_versions` (plutôt que de simples
 * colonnes `activated_at`/`activated_by`) pour porter explicitement
 * `replaced_value_version_id` — la ligne d'audit qui relie chaque
 * activation à la version qu'elle a remplacée, jamais reconstituée par
 * déduction.
 *
 * « État de propagation » (ADR-0002 §8) volontairement absent ici : ce
 * monolithe n'a qu'un seul nœud d'écriture — aucune propagation
 * multi-nœud à attester. Porte de reprise si une architecture distribuée
 * était un jour adoptée (CLAUDE.md §2 l'interdit sans nouvelle décision).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration.activations', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('value_version_id')
                ->unique()
                ->constrained('configuration.value_versions')
                ->restrictOnDelete();

            $table->foreignUuid('activated_by_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('activated_at');
            $table->uuid('correlation_id');

            $table->foreignUuid('replaced_value_version_id')
                ->nullable()
                ->constrained('configuration.value_versions')
                ->restrictOnDelete();

            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_activations_self_activation()
            RETURNS trigger AS $$
            DECLARE
                author_id uuid;
            BEGIN
                SELECT author_person_account_link_id INTO author_id
                FROM configuration.value_versions
                WHERE id = NEW.value_version_id;

                IF author_id = NEW.activated_by_person_account_link_id THEN
                    RAISE EXCEPTION 'configuration: l''auteur d''une ValueVersion ne peut activer sa propre proposition (ADR-0002 §3.2)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER activations_prevent_self_activation BEFORE INSERT ON configuration.activations '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_activations_self_activation()'
        );

        // Preuve d'activation : jamais modifiée ni supprimée physiquement,
        // sur le modèle exact de `prevent_grants_deletion`.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_activations_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'configuration: une activation ne peut jamais être modifiée ni supprimée (ADR-0002 §8)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER activations_prevent_update BEFORE UPDATE ON configuration.activations '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_activations_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER activations_prevent_deletion BEFORE DELETE ON configuration.activations '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_activations_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS activations_prevent_deletion ON configuration.activations');
        DB::statement('DROP TRIGGER IF EXISTS activations_prevent_update ON configuration.activations');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_activations_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS activations_prevent_self_activation ON configuration.activations');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_activations_self_activation()');
        Schema::dropIfExists('configuration.activations');
    }
};
