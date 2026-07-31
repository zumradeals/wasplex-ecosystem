<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Machine d'états explicite de `advertising.subscription_purchases`, sur
 * le modèle exact de
 * `advertising.enforce_advertiser_wallet_deposit_state_machine()`
 * (migration `2026_07_31_100003`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.enforce_subscription_purchase_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.state = OLD.state THEN
                    RAISE EXCEPTION 'advertising: une transition d''achat d''abonnement vers le même état est refusée (subscription_purchase %)', OLD.id;
                END IF;

                IF NOT (
                    (OLD.state = 'draft' AND NEW.state = 'awaiting_provider') OR
                    (OLD.state = 'awaiting_provider' AND NEW.state IN ('pending', 'completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'pending' AND NEW.state IN ('completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'unknown_reconciliation' AND NEW.state IN ('completed', 'failed'))
                ) THEN
                    RAISE EXCEPTION 'advertising: transition d''achat d''abonnement refusée : % -> % (subscription_purchase %)', OLD.state, NEW.state, OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER subscription_purchases_enforce_state_machine BEFORE UPDATE ON advertising.subscription_purchases '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.enforce_subscription_purchase_state_machine()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION advertising.prevent_subscription_purchases_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'advertising: un achat d''abonnement ne peut jamais être supprimé physiquement';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER subscription_purchases_prevent_deletion BEFORE DELETE ON advertising.subscription_purchases '
            .'FOR EACH ROW EXECUTE FUNCTION advertising.prevent_subscription_purchases_deletion()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS subscription_purchases_prevent_deletion ON advertising.subscription_purchases');
        DB::statement('DROP FUNCTION IF EXISTS advertising.prevent_subscription_purchases_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS subscription_purchases_enforce_state_machine ON advertising.subscription_purchases');
        DB::statement('DROP FUNCTION IF EXISTS advertising.enforce_subscription_purchase_state_machine()');
    }
};
