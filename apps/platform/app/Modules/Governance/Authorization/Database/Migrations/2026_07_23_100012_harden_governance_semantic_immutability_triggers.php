<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reprise de TD-0001-C et TD-0001-D (technical-debt/TD-0001-authorization-core-follow-ups.md).
 *
 * Règle harmonisée d'immutabilité sémantique, désormais uniforme sur les
 * quatre tables de définitions versionnées (`capability_definitions`,
 * `policy_versions`, `role_templates`, `purpose_definitions`) : une ligne
 * `active` OU `retired` ne peut plus jamais voir ses champs sémantiques
 * modifiés — seule une transition d'état et, sur une ligne `retired`,
 * une clôture de période (`effective_to`) restent permises. Seule une
 * nouvelle version permet une évolution. `policy_versions` appliquait déjà
 * cette règle sans condition d'état (contenu et checksum jamais modifiés,
 * quel que soit l'état) ; elle n'a donc pas besoin d'être étendue ici.
 *
 * TD-0001-C : les déclencheurs protégeant `role_template_capabilities` et
 * `capability_purposes` ne vérifiaient, sur UPDATE, que le nouveau parent
 * (`COALESCE(NEW..., OLD...)` résout toujours vers NEW lors d'un UPDATE).
 * Un déplacement de liaison depuis un parent actif vers un parent inactif
 * pouvait donc contourner la protection. Les deux fonctions sont
 * remplacées ici pour vérifier séparément l'ancien ET le nouveau parent :
 * si l'un des deux est actif, l'opération est refusée.
 *
 * Historique figé localement, sans dépendance aux enums applicatifs
 * (revue SIRR P003-A.2 §3, reconduite pour ce module).
 */
return new class extends Migration
{
    public function up(): void
    {
        // TD-0001-D — capability_definitions et role_templates : la règle
        // d'immutabilité active s'étend désormais à l'état retired. Les
        // champs vérifiés sont inchangés (effective_to n'y a jamais figuré,
        // une clôture de période reste donc permise sur les deux états).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_capability_definitions_active_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state IN ('active', 'retired') AND (
                    NEW.stable_key IS DISTINCT FROM OLD.stable_key OR
                    NEW.version IS DISTINCT FROM OLD.version OR
                    NEW.domain IS DISTINCT FROM OLD.domain OR
                    NEW.action IS DISTINCT FROM OLD.action OR
                    NEW.description IS DISTINCT FROM OLD.description OR
                    NEW.operation IS DISTINCT FROM OLD.operation OR
                    NEW.risk_class IS DISTINCT FROM OLD.risk_class OR
                    NEW.purpose_required IS DISTINCT FROM OLD.purpose_required OR
                    NEW.approval_required IS DISTINCT FROM OLD.approval_required OR
                    NEW.minimum_session_assurance IS DISTINCT FROM OLD.minimum_session_assurance OR
                    NEW.effective_from IS DISTINCT FROM OLD.effective_from
                ) THEN
                    RAISE EXCEPTION 'governance: une capability_definitions active ou retirée ne peut voir ses champs sémantiques modifiés ; créez une nouvelle version (TD-0001-D)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_role_templates_active_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state IN ('active', 'retired') AND (
                    NEW.stable_key IS DISTINCT FROM OLD.stable_key OR
                    NEW.version IS DISTINCT FROM OLD.version OR
                    NEW.label IS DISTINCT FROM OLD.label OR
                    NEW.description IS DISTINCT FROM OLD.description OR
                    NEW.organization_category IS DISTINCT FROM OLD.organization_category OR
                    NEW.effective_from IS DISTINCT FROM OLD.effective_from
                ) THEN
                    RAISE EXCEPTION 'governance: un role_templates actif ou retiré ne peut voir ses champs sémantiques modifiés ; créez une nouvelle version (TD-0001-D)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // TD-0001-D — purpose_definitions : déclencheur manquant, ajouté
        // symétriquement à celui de capability_definitions.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_purpose_definitions_semantic_mutation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state IN ('active', 'retired') AND (
                    NEW.stable_key IS DISTINCT FROM OLD.stable_key OR
                    NEW.version IS DISTINCT FROM OLD.version OR
                    NEW.description IS DISTINCT FROM OLD.description OR
                    NEW.effective_from IS DISTINCT FROM OLD.effective_from
                ) THEN
                    RAISE EXCEPTION 'governance: une purpose_definitions active ou retirée ne peut voir ses champs sémantiques modifiés ; créez une nouvelle version (TD-0001-D)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER purpose_definitions_prevent_semantic_mutation BEFORE UPDATE ON governance.purpose_definitions '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_purpose_definitions_semantic_mutation()'
        );

        // TD-0001-C — role_template_capabilities : vérifie désormais
        // séparément l'ancien et le nouveau parent sur UPDATE.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_active_role_template_capabilities_mutation()
            RETURNS trigger AS $$
            DECLARE
                old_template_state text;
                new_template_state text;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    SELECT state INTO old_template_state FROM governance.role_templates WHERE id = OLD.role_template_id;

                    IF old_template_state = 'active' THEN
                        RAISE EXCEPTION 'governance: le catalogue de capacités d''un role_templates actif ne peut pas être modifié ; créez une nouvelle version (P003-B1.1 §4)';
                    END IF;

                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    SELECT state INTO old_template_state FROM governance.role_templates WHERE id = OLD.role_template_id;

                    IF old_template_state = 'active' THEN
                        RAISE EXCEPTION 'governance: le catalogue de capacités d''un role_templates actif ne peut pas être modifié ; créez une nouvelle version (TD-0001-C)';
                    END IF;
                END IF;

                SELECT state INTO new_template_state FROM governance.role_templates WHERE id = NEW.role_template_id;

                IF new_template_state = 'active' THEN
                    RAISE EXCEPTION 'governance: le catalogue de capacités d''un role_templates actif ne peut pas être modifié ; créez une nouvelle version (P003-B1.1 §4)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // TD-0001-C — capability_purposes : même correction.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_active_capability_purposes_mutation()
            RETURNS trigger AS $$
            DECLARE
                old_capability_state text;
                new_capability_state text;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    SELECT state INTO old_capability_state FROM governance.capability_definitions WHERE id = OLD.capability_definition_id;

                    IF old_capability_state = 'active' THEN
                        RAISE EXCEPTION 'governance: le catalogue de finalités d''une capability_definitions active ne peut pas être modifié ; créez une nouvelle version (P003-B1.3 §5)';
                    END IF;

                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    SELECT state INTO old_capability_state FROM governance.capability_definitions WHERE id = OLD.capability_definition_id;

                    IF old_capability_state = 'active' THEN
                        RAISE EXCEPTION 'governance: le catalogue de finalités d''une capability_definitions active ne peut pas être modifié ; créez une nouvelle version (TD-0001-C)';
                    END IF;
                END IF;

                SELECT state INTO new_capability_state FROM governance.capability_definitions WHERE id = NEW.capability_definition_id;

                IF new_capability_state = 'active' THEN
                    RAISE EXCEPTION 'governance: le catalogue de finalités d''une capability_definitions active ne peut pas être modifié ; créez une nouvelle version (P003-B1.3 §5)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS purpose_definitions_prevent_semantic_mutation ON governance.purpose_definitions');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_purpose_definitions_semantic_mutation()');

        // Les fonctions capability_definitions/role_templates/role_template_capabilities/
        // capability_purposes sont remplacées en place (CREATE OR REPLACE) :
        // leurs déclencheurs restent ceux créés par la migration 100011,
        // qu'il n'appartient pas à cette migration de supprimer.
    }
};
