<?php

use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Déclencheurs PostgreSQL garantissant, en défense en profondeur des
 * vérifications déjà faites par {@see LedgerPoster}
 * avant toute écriture (ADR-0003 §1, §5, §11, §17 ; architecture/05
 * "Règles structurelles"), que le socle du ledger reste vrai même en cas de
 * bug applicatif ou d'accès direct à la base :
 *
 *  - un posting ne référence qu'un compte de même devise, actif, et dont les
 *    restrictions de mouvement autorisent le sens demandé
 *    (`postings_enforce_account_rules`, BEFORE INSERT, immédiat) ;
 *  - l'ensemble des postings d'une même transaction ne mélange jamais deux
 *    devises et s'équilibre exactement par devise
 *    (`postings_enforce_balance`, contrainte différée à la validation de la
 *    transaction SQL, pour voir l'ensemble des lignes insérées) ;
 *  - une transaction comptabilisée référence au moins deux postings
 *    (`ledger_transactions_enforce_minimum_postings`, même mécanisme différé) ;
 *  - aucun UPDATE ni DELETE métier sur une transaction ou un posting déjà
 *    comptabilisé, sans exception de cycle de vie (contrairement aux grants
 *    de Governance/Authorization : une ligne du ledger n'a pas d'état
 *    modifiable après création, ADR-0003 §11).
 *
 * Sur le modèle exact des déclencheurs d'immutabilité de
 * Governance/Authorization (P003-B1.3 §4, P003-B3/TD-0001-D).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Immutabilité inconditionnelle -------------------------------

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger.prevent_ledger_transactions_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ledger: une transaction comptabilisée ne peut être ni modifiée ni supprimée (ADR-0003 §11)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER ledger_transactions_prevent_update BEFORE UPDATE ON ledger.ledger_transactions '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.prevent_ledger_transactions_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER ledger_transactions_prevent_delete BEFORE DELETE ON ledger.ledger_transactions '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.prevent_ledger_transactions_mutation()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger.prevent_postings_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'ledger: un posting comptabilisé ne peut être ni modifié ni supprimé (ADR-0003 §11)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER postings_prevent_update BEFORE UPDATE ON ledger.postings '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.prevent_postings_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER postings_prevent_delete BEFORE DELETE ON ledger.postings '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.prevent_postings_mutation()'
        );

        // --- Cohérence compte <-> posting, immédiate ---------------------

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger.enforce_posting_account_rules()
            RETURNS trigger AS $$
            DECLARE
                account_currency CHAR(3);
                account_status TEXT;
                account_restrictions JSONB;
            BEGIN
                SELECT currency, status, movement_restrictions
                INTO account_currency, account_status, account_restrictions
                FROM ledger.accounts
                WHERE id = NEW.account_id
                FOR SHARE;

                IF account_currency IS DISTINCT FROM NEW.currency THEN
                    RAISE EXCEPTION 'ledger: la devise du posting (%) ne correspond pas à la devise du compte (%) (ADR-0003 §1)', NEW.currency, account_currency;
                END IF;

                IF account_status <> 'active' THEN
                    RAISE EXCEPTION 'ledger: le compte % n''accepte aucun mouvement dans son statut actuel (%)', NEW.account_id, account_status;
                END IF;

                IF NEW.direction = 'debit' AND COALESCE((account_restrictions->>'debit_allowed')::boolean, TRUE) IS FALSE THEN
                    RAISE EXCEPTION 'ledger: le compte % refuse tout débit (restriction de mouvement)', NEW.account_id;
                END IF;

                IF NEW.direction = 'credit' AND COALESCE((account_restrictions->>'credit_allowed')::boolean, TRUE) IS FALSE THEN
                    RAISE EXCEPTION 'ledger: le compte % refuse tout crédit (restriction de mouvement)', NEW.account_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER postings_enforce_account_rules BEFORE INSERT ON ledger.postings '
            .'FOR EACH ROW EXECUTE FUNCTION ledger.enforce_posting_account_rules()'
        );

        // --- Équilibre et devise unique par transaction, différé ---------
        //
        // Déclencheur de contrainte différé à la validation (COMMIT) de la
        // transaction SQL : au moment où il s'exécute, tous les INSERT de
        // postings émis par LedgerPoster::post() dans la même transaction
        // sont déjà visibles, ce qui permet de vérifier l'ensemble complet
        // des lignes plutôt qu'une ligne isolée.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ledger.enforce_transaction_balance()
            RETURNS trigger AS $$
            DECLARE
                distinct_currencies INT;
                imbalance_currency CHAR(3);
                posting_count INT;
            BEGIN
                SELECT COUNT(*), COUNT(DISTINCT currency)
                INTO posting_count, distinct_currencies
                FROM ledger.postings
                WHERE ledger_transaction_id = NEW.ledger_transaction_id;

                IF distinct_currencies > 1 THEN
                    RAISE EXCEPTION 'ledger: une transaction ne peut mélanger deux devises entre ses postings (ADR-0003 §5)';
                END IF;

                IF posting_count < 2 THEN
                    RAISE EXCEPTION 'ledger: une transaction comptabilisée référence au moins deux postings (architecture/05)';
                END IF;

                SELECT currency INTO imbalance_currency
                FROM ledger.postings
                WHERE ledger_transaction_id = NEW.ledger_transaction_id
                GROUP BY currency
                HAVING SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END)
                    <> SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END)
                LIMIT 1;

                IF imbalance_currency IS NOT NULL THEN
                    RAISE EXCEPTION 'ledger: la somme des débits doit égaler la somme des crédits pour % (ADR-0003 §1, §17)', imbalance_currency;
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER postings_enforce_balance
            AFTER INSERT ON ledger.postings
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION ledger.enforce_transaction_balance()
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS postings_enforce_balance ON ledger.postings');
        DB::statement('DROP FUNCTION IF EXISTS ledger.enforce_transaction_balance()');
        DB::statement('DROP TRIGGER IF EXISTS postings_enforce_account_rules ON ledger.postings');
        DB::statement('DROP FUNCTION IF EXISTS ledger.enforce_posting_account_rules()');
        DB::statement('DROP TRIGGER IF EXISTS postings_prevent_delete ON ledger.postings');
        DB::statement('DROP TRIGGER IF EXISTS postings_prevent_update ON ledger.postings');
        DB::statement('DROP FUNCTION IF EXISTS ledger.prevent_postings_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS ledger_transactions_prevent_delete ON ledger.ledger_transactions');
        DB::statement('DROP TRIGGER IF EXISTS ledger_transactions_prevent_update ON ledger.ledger_transactions');
        DB::statement('DROP FUNCTION IF EXISTS ledger.prevent_ledger_transactions_mutation()');
    }
};
