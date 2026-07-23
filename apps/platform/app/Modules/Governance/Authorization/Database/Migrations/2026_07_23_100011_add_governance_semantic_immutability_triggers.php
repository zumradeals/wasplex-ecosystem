<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Immutabilité sémantique des versions actives (P003-B1.1 §4).
 *
 * Une capacité ou un rôle modèle actif ne peut voir aucun de ses champs
 * sémantiques modifiés : seul le passage d'état (par exemple vers `retired`)
 * est permis, dans une mise à jour qui ne change rien d'autre. Le contenu et
 * le checksum d'une politique ne sont jamais modifiés une fois créés, qu'ils
 * soient encore actifs ou déjà retirés — les décisions passées les
 * référencent et doivent rester reconstructibles. Le catalogue de capacités
 * d'un rôle modèle actif est figé : toute évolution exige une nouvelle
 * version du rôle (déjà démontré par les tests P003-B1 §19).
 *
 * Historique figé localement, sans dépendance aux enums applicatifs
 * (revue SIRR P003-A.2 §3, reconduite pour ce module).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_capability_definitions_active_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state = 'active' AND (
                    NEW.stable_key IS DISTINCT FROM OLD.stable_key OR
                    NEW.version IS DISTINCT FROM OLD.version OR
                    NEW.domain IS DISTINCT FROM OLD.domain OR
                    NEW.action IS DISTINCT FROM OLD.action OR
                    NEW.description IS DISTINCT FROM OLD.description OR
                    NEW.risk_class IS DISTINCT FROM OLD.risk_class OR
                    NEW.purpose_required IS DISTINCT FROM OLD.purpose_required OR
                    NEW.approval_required IS DISTINCT FROM OLD.approval_required OR
                    NEW.minimum_session_assurance IS DISTINCT FROM OLD.minimum_session_assurance OR
                    NEW.effective_from IS DISTINCT FROM OLD.effective_from
                ) THEN
                    RAISE EXCEPTION 'governance: une capability_definitions active ne peut voir ses champs sémantiques modifiés ; créez une nouvelle version (P003-B1.1 §4)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER capability_definitions_prevent_active_mutation BEFORE UPDATE ON governance.capability_definitions '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_capability_definitions_active_mutation()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_policy_versions_content_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.checksum IS DISTINCT FROM OLD.checksum
                   OR NEW.stable_key IS DISTINCT FROM OLD.stable_key
                   OR NEW.version IS DISTINCT FROM OLD.version
                   OR NEW.effective_from IS DISTINCT FROM OLD.effective_from
                THEN
                    RAISE EXCEPTION 'governance: le contenu et le checksum de policy_versions ne sont jamais modifiés rétroactivement (P003-B1.1 §4)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER policy_versions_prevent_content_mutation BEFORE UPDATE ON governance.policy_versions '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_policy_versions_content_mutation()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_role_templates_active_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state = 'active' AND (
                    NEW.stable_key IS DISTINCT FROM OLD.stable_key OR
                    NEW.version IS DISTINCT FROM OLD.version OR
                    NEW.label IS DISTINCT FROM OLD.label OR
                    NEW.description IS DISTINCT FROM OLD.description OR
                    NEW.organization_category IS DISTINCT FROM OLD.organization_category OR
                    NEW.effective_from IS DISTINCT FROM OLD.effective_from
                ) THEN
                    RAISE EXCEPTION 'governance: un role_templates actif ne peut voir ses champs sémantiques modifiés ; créez une nouvelle version (P003-B1.1 §4)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER role_templates_prevent_active_mutation BEFORE UPDATE ON governance.role_templates '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_role_templates_active_mutation()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_active_role_template_capabilities_mutation()
            RETURNS trigger AS $$
            DECLARE
                template_state text;
            BEGIN
                SELECT state INTO template_state
                FROM governance.role_templates
                WHERE id = COALESCE(NEW.role_template_id, OLD.role_template_id);

                IF template_state = 'active' THEN
                    RAISE EXCEPTION 'governance: le catalogue de capacités d''un role_templates actif ne peut pas être modifié ; créez une nouvelle version (P003-B1.1 §4)';
                END IF;

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER role_template_capabilities_prevent_active_mutation '
            .'BEFORE INSERT OR UPDATE OR DELETE ON governance.role_template_capabilities '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_active_role_template_capabilities_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS role_template_capabilities_prevent_active_mutation ON governance.role_template_capabilities');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_active_role_template_capabilities_mutation()');

        DB::statement('DROP TRIGGER IF EXISTS role_templates_prevent_active_mutation ON governance.role_templates');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_role_templates_active_mutation()');

        DB::statement('DROP TRIGGER IF EXISTS policy_versions_prevent_content_mutation ON governance.policy_versions');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_policy_versions_content_mutation()');

        DB::statement('DROP TRIGGER IF EXISTS capability_definitions_prevent_active_mutation ON governance.capability_definitions');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_capability_definitions_active_mutation()');
    }
};
