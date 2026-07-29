<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Machines d'états explicites de `alerts.cases` (par `nature`, jamais
 * mélangées) et `alerts.institution_dispatches` — sur le modèle exact de
 * `configuration.enforce_value_version_state_machine()`. Toute transition
 * absente de ces graphes est refusée côté serveur (AMD-0007 §5 ;
 * ecosystem/alertes/02 §3 : « une transition invalide est rejetée côté
 * serveur »). Aucune transition vers le même état n'est permise : une
 * correction passe par un nouvel événement compensatoire
 * (`alerts.case_events`), jamais par une réécriture d'état.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION alerts.enforce_case_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.nature IS DISTINCT FROM OLD.nature THEN
                    RAISE EXCEPTION 'alerts: la nature d''un dossier ne change jamais (community/sos)';
                END IF;

                IF NEW.state = OLD.state THEN
                    RAISE EXCEPTION 'alerts: une transition vers le même état est refusée (case %)', OLD.id;
                END IF;

                IF OLD.nature = 'community' THEN
                    IF NOT (
                        (OLD.state = 'draft' AND NEW.state IN ('submitted', 'withdrawn')) OR
                        (OLD.state = 'submitted' AND NEW.state IN ('under_review', 'withdrawn')) OR
                        (OLD.state = 'under_review' AND NEW.state IN ('published', 'restricted', 'rejected')) OR
                        (OLD.state = 'published' AND NEW.state IN ('matched', 'expired', 'withdrawn')) OR
                        (OLD.state = 'restricted' AND NEW.state IN ('matched', 'expired', 'withdrawn')) OR
                        (OLD.state = 'matched' AND NEW.state IN ('restitution_scheduled', 'disputed')) OR
                        (OLD.state = 'restitution_scheduled' AND NEW.state IN ('resolved', 'disputed', 'expired')) OR
                        (OLD.state = 'disputed' AND NEW.state = 'resolved')
                    ) THEN
                        RAISE EXCEPTION 'alerts: transition communautaire refusée : % -> % (case %)', OLD.state, NEW.state, OLD.id;
                    END IF;
                ELSIF OLD.nature = 'sos' THEN
                    IF NOT (
                        (OLD.state = 'created' AND NEW.state IN ('transmitted', 'cancelled', 'impossible')) OR
                        (OLD.state = 'transmitted' AND NEW.state IN ('received', 'unanswered', 'refused', 'impossible')) OR
                        (OLD.state = 'received' AND NEW.state IN ('accepted', 'transferred', 'refused')) OR
                        (OLD.state = 'accepted' AND NEW.state IN ('processing', 'transferred')) OR
                        (OLD.state = 'processing' AND NEW.state IN ('resolved', 'transferred', 'disputed', 'closed_unresolved')) OR
                        (OLD.state = 'unanswered' AND NEW.state IN ('transferred', 'cancelled', 'impossible')) OR
                        (OLD.state = 'refused' AND NEW.state IN ('transferred', 'cancelled')) OR
                        (OLD.state = 'transferred' AND NEW.state IN ('transmitted', 'resolved', 'closed_unresolved')) OR
                        (OLD.state = 'disputed' AND NEW.state IN ('resolved', 'closed_unresolved'))
                    ) THEN
                        RAISE EXCEPTION 'alerts: transition SOS refusée : % -> % (case %)', OLD.state, NEW.state, OLD.id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER cases_enforce_state_machine BEFORE UPDATE ON alerts.cases '
            .'FOR EACH ROW EXECUTE FUNCTION alerts.enforce_case_state_machine()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION alerts.prevent_cases_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'alerts: un dossier ne peut jamais être supprimé physiquement (AMD-0007 §15)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER cases_prevent_deletion BEFORE DELETE ON alerts.cases '
            .'FOR EACH ROW EXECUTE FUNCTION alerts.prevent_cases_deletion()'
        );

        // `institution_dispatches` (ecosystem/institutions/01 §6) : « la
        // transmission n'est pas une réception ; la réception n'est pas une
        // acceptation ; l'acceptation n'est pas une intervention réussie » —
        // jamais transformé automatiquement.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION alerts.enforce_dispatch_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.state = OLD.state THEN
                    RAISE EXCEPTION 'alerts: une transition vers le même état de dispatch est refusée (dispatch %)', OLD.id;
                END IF;

                IF NOT (
                    (OLD.state = 'created' AND NEW.state IN ('transmitted', 'cancelled', 'impossible')) OR
                    (OLD.state = 'transmitted' AND NEW.state IN ('received', 'unanswered', 'refused', 'impossible')) OR
                    (OLD.state = 'received' AND NEW.state IN ('accepted', 'transferred', 'refused')) OR
                    (OLD.state = 'accepted' AND NEW.state IN ('processing', 'transferred')) OR
                    (OLD.state = 'processing' AND NEW.state IN ('resolved', 'transferred', 'closed_unresolved')) OR
                    (OLD.state = 'unanswered' AND NEW.state IN ('transferred', 'cancelled', 'impossible')) OR
                    (OLD.state = 'refused' AND NEW.state IN ('transferred', 'cancelled')) OR
                    (OLD.state = 'transferred' AND NEW.state IN ('cancelled'))
                ) THEN
                    RAISE EXCEPTION 'alerts: transition de dispatch refusée : % -> % (dispatch %)', OLD.state, NEW.state, OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER dispatches_enforce_state_machine BEFORE UPDATE ON alerts.institution_dispatches '
            .'FOR EACH ROW EXECUTE FUNCTION alerts.enforce_dispatch_state_machine()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS dispatches_enforce_state_machine ON alerts.institution_dispatches');
        DB::statement('DROP FUNCTION IF EXISTS alerts.enforce_dispatch_state_machine()');
        DB::statement('DROP TRIGGER IF EXISTS cases_prevent_deletion ON alerts.cases');
        DB::statement('DROP FUNCTION IF EXISTS alerts.prevent_cases_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS cases_enforce_state_machine ON alerts.cases');
        DB::statement('DROP FUNCTION IF EXISTS alerts.enforce_case_state_machine()');
    }
};
