<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Machine d'états explicite de `ledger.wallet_deposits`
 * (ecosystem/wallet/05 §2), sur le modèle exact de
 * `alerts.enforce_case_state_machine()`. Toute transition absente de ce
 * graphe est refusée côté serveur. `completed`/`failed` sont terminaux :
 * aucune ligne dans cet état ne peut plus changer d'état, et aucun dépôt
 * n'est jamais supprimé physiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger.enforce_wallet_deposit_state_machine()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.state = OLD.state THEN
                    RAISE EXCEPTION 'ledger: une transition de dépôt vers le même état est refusée (deposit %)', OLD.id;
                END IF;

                IF NOT (
                    (OLD.state = 'draft' AND NEW.state = 'awaiting_provider') OR
                    (OLD.state = 'awaiting_provider' AND NEW.state IN ('pending', 'completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'pending' AND NEW.state IN ('completed', 'failed', 'unknown_reconciliation')) OR
                    (OLD.state = 'unknown_reconciliation' AND NEW.state IN ('completed', 'failed'))
                ) THEN
                    RAISE EXCEPTION 'ledger: transition de dépôt refusée : % -> % (deposit %)', OLD.state, NEW.state, OLD.id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER wallet_deposits_enforce_state_machine BEFORE UPDATE ON ledger.wallet_deposits '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.enforce_wallet_deposit_state_machine()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger.prevent_wallet_deposits_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ledger: un dépôt ne peut jamais être supprimé physiquement (AMD-0017)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER wallet_deposits_prevent_deletion BEFORE DELETE ON ledger.wallet_deposits '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.prevent_wallet_deposits_deletion()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS wallet_deposits_prevent_deletion ON ledger.wallet_deposits');
        DB::statement('DROP FUNCTION IF EXISTS ledger.prevent_wallet_deposits_deletion()');
        DB::statement('DROP TRIGGER IF EXISTS wallet_deposits_enforce_state_machine ON ledger.wallet_deposits');
        DB::statement('DROP FUNCTION IF EXISTS ledger.enforce_wallet_deposit_state_machine()');
    }
};
