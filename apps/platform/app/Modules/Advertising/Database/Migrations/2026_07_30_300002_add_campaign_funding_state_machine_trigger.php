<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Machine d'états explicite de `advertising.campaign_fundings`, sur le
 * modèle exact de `ledger.enforce_wallet_deposit_state_machine()`
 * (migration `2026_07_30_100003`). `completed`/`failed` sont terminaux :
 * aucune ligne dans cet état ne peut plus changer d'état, et aucun
 * financement n'est jamais supprimé physiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.enforce_campaign_funding_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.state = OLD.state THEN
                    RAISE EXCEPTION 'advertising: une transition de financement vers le même état est refusée (campaign_funding %)', OLD.id;
                END IF;

                IF NOT (
                    (OLD.state = 'draft' AND NEW.state = 'awaiting_provider') OR
                    (OLD.state = 'awaiting_provider' AND NEW.state IN ('pending', 'completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'pending' AND NEW.state IN ('completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'unknown_reconciliation' AND NEW.state IN ('completed', 'failed'))
                ) THEN
                    RAISE EXCEPTION 'advertising: transition de financement refusée : % -> % (campaign_funding %)', OLD.state, NEW.state, OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER campaign_fundings_enforce_state_machine BEFORE UPDATE ON advertising.campaign_fundings '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.enforce_campaign_funding_state_machine()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.prevent_campaign_fundings_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'advertising: un financement de campagne ne peut jamais être supprimé physiquement';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER campaign_fundings_prevent_deletion BEFORE DELETE ON advertising.campaign_fundings '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.prevent_campaign_fundings_deletion()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS campaign_fundings_prevent_deletion ON advertising.campaign_fundings');
        DB::statement('DROP FUNCTION IF EXISTS advertising.prevent_campaign_fundings_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS campaign_fundings_enforce_state_machine ON advertising.campaign_fundings');
        DB::statement('DROP FUNCTION IF EXISTS advertising.enforce_campaign_funding_state_machine()');
    }
};
