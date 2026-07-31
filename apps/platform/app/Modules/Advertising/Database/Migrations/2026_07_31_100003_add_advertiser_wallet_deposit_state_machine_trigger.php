<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Machine d'états explicite de `advertising.advertiser_wallet_deposits`, sur
 * le modèle exact de
 * `advertising.enforce_campaign_funding_state_machine()` (migration
 * `2026_07_30_300002`). `completed`/`failed` sont terminaux : aucune ligne
 * dans cet état ne peut plus changer d'état, et aucun dépôt n'est jamais
 * supprimé physiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.enforce_advertiser_wallet_deposit_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.state = OLD.state THEN
                    RAISE EXCEPTION 'advertising: une transition de dépôt Wallet vers le même état est refusée (advertiser_wallet_deposit %)', OLD.id;
                END IF;

                IF NOT (
                    (OLD.state = 'draft' AND NEW.state = 'awaiting_provider') OR
                    (OLD.state = 'awaiting_provider' AND NEW.state IN ('pending', 'completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'pending' AND NEW.state IN ('completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'unknown_reconciliation' AND NEW.state IN ('completed', 'failed'))
                ) THEN
                    RAISE EXCEPTION 'advertising: transition de dépôt Wallet refusée : % -> % (advertiser_wallet_deposit %)', OLD.state, NEW.state, OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER advertiser_wallet_deposits_enforce_state_machine BEFORE UPDATE ON advertising.advertiser_wallet_deposits '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.enforce_advertiser_wallet_deposit_state_machine()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.prevent_advertiser_wallet_deposits_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'advertising: un dépôt Wallet annonceur ne peut jamais être supprimé physiquement';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER advertiser_wallet_deposits_prevent_deletion BEFORE DELETE ON advertising.advertiser_wallet_deposits '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.prevent_advertiser_wallet_deposits_deletion()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS advertiser_wallet_deposits_prevent_deletion ON advertising.advertiser_wallet_deposits');
        DB::statement('DROP FUNCTION IF EXISTS advertising.prevent_advertiser_wallet_deposits_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS advertiser_wallet_deposits_enforce_state_machine ON advertising.advertiser_wallet_deposits');
        DB::statement('DROP FUNCTION IF EXISTS advertising.enforce_advertiser_wallet_deposit_state_machine()');
    }
};
