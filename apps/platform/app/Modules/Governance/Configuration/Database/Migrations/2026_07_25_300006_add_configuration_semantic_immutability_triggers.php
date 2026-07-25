<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Immuabilité sémantique et machine d'états de `configuration.definitions`
 * et `configuration.value_versions` (W2, ADR-0002 §4, §8) — sur le modèle
 * exact des déclencheurs de Governance/Authorization
 * (`grants_prevent_semantic_mutation`, `enforce_grant_state_machine`,
 * `prevent_grants_deletion`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Une Definition active ou retirée ne voit plus ses champs
        // sémantiques modifiés (une nouvelle version remplace la
        // précédente) — même garde que `role_templates`/`capability_definitions`
        // (état actif ou retiré).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_definitions_semantic_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state NOT IN ('active', 'retired') THEN
                    RETURN NEW;
                END IF;

                IF NEW.stable_key IS DISTINCT FROM OLD.stable_key OR
                   NEW.version IS DISTINCT FROM OLD.version OR
                   NEW.domain IS DISTINCT FROM OLD.domain OR
                   NEW.level IS DISTINCT FROM OLD.level OR
                   NEW.value_type IS DISTINCT FROM OLD.value_type OR
                   NEW.unit IS DISTINCT FROM OLD.unit OR
                   NEW.constraints IS DISTINCT FROM OLD.constraints OR
                   NEW.description IS DISTINCT FROM OLD.description OR
                   NEW.effective_from IS DISTINCT FROM OLD.effective_from
                THEN
                    RAISE EXCEPTION 'configuration: une definition active ou retirée ne peut voir ses champs sémantiques modifiés ; créez une nouvelle version (ADR-0002 §8)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER definitions_prevent_semantic_mutation BEFORE UPDATE ON configuration.definitions '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_definitions_semantic_mutation()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_definitions_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'configuration: une definition ne peut jamais être supprimée physiquement (ADR-0002 §8)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER definitions_prevent_deletion BEFORE DELETE ON configuration.definitions '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_definitions_deletion()'
        );

        // ValueVersion : le contenu (valeur, justification) n'est librement
        // composable qu'à l'état `draft` — dès `in_review`, des approbations
        // s'accumulent sur ce contenu précis ; le modifier ensuite
        // invaliderait silencieusement les décisions déjà prises.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_value_versions_semantic_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state = 'draft' THEN
                    RETURN NEW;
                END IF;

                IF NEW.definition_id IS DISTINCT FROM OLD.definition_id OR
                   NEW.version IS DISTINCT FROM OLD.version OR
                   NEW.value IS DISTINCT FROM OLD.value OR
                   NEW.justification IS DISTINCT FROM OLD.justification OR
                   NEW.author_person_account_link_id IS DISTINCT FROM OLD.author_person_account_link_id
                THEN
                    RAISE EXCEPTION 'configuration: une ValueVersion hors de l''état draft ne peut voir son contenu modifié ; proposez une nouvelle version (ADR-0002 §4, §8)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER value_versions_prevent_semantic_mutation BEFORE UPDATE ON configuration.value_versions '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_value_versions_semantic_mutation()'
        );

        // Machine d'états explicite (cycle réduit au noyau, voir la
        // migration de création de la table) :
        //   draft      -> in_review, cancelled
        //   in_review  -> approved, rejected, cancelled
        //   approved   -> active, cancelled
        //   active     -> replaced
        //   replaced, rejected, cancelled : terminaux.
        // Toute transition vers le même état (y compris active -> active,
        // aucune réactivation répétée) est refusée, même discipline que
        // `enforce_grant_state_machine`.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.enforce_value_version_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.state = OLD.state THEN
                    -- `draft` reste librement composable (contenu réédité
                    -- sans changer d'état) : seule une transition « vers le
                    -- même état non-draft » est un rejeu jamais permis
                    -- (ex. active -> active, aucune réactivation répétée).
                    IF OLD.state = 'draft' THEN
                        RETURN NEW;
                    END IF;

                    RAISE EXCEPTION 'configuration: une transition vers le même état de ValueVersion est refusée, y compris active -> active (ADR-0002 §8)';
                END IF;

                IF NOT (
                    (OLD.state = 'draft' AND NEW.state IN ('in_review', 'cancelled')) OR
                    (OLD.state = 'in_review' AND NEW.state IN ('approved', 'rejected', 'cancelled')) OR
                    (OLD.state = 'approved' AND NEW.state IN ('active', 'cancelled')) OR
                    (OLD.state = 'active' AND NEW.state = 'replaced')
                ) THEN
                    RAISE EXCEPTION 'configuration: transition d''état de ValueVersion refusée : % -> % (ADR-0002 §8)', OLD.state, NEW.state;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER value_versions_enforce_state_machine BEFORE UPDATE ON configuration.value_versions '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.enforce_value_version_state_machine()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_value_versions_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'configuration: une ValueVersion ne peut jamais être supprimée physiquement (ADR-0002 §8)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER value_versions_prevent_deletion BEFORE DELETE ON configuration.value_versions '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_value_versions_deletion()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS value_versions_prevent_deletion ON configuration.value_versions');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_value_versions_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS value_versions_enforce_state_machine ON configuration.value_versions');
        DB::statement('DROP FUNCTION IF EXISTS configuration.enforce_value_version_state_machine()');
        DB::statement('DROP TRIGGER IF EXISTS value_versions_prevent_semantic_mutation ON configuration.value_versions');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_value_versions_semantic_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS definitions_prevent_deletion ON configuration.definitions');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_definitions_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS definitions_prevent_semantic_mutation ON configuration.definitions');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_definitions_semantic_mutation()');
    }
};
