<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `ledger.wallet_deposits` (AMD-0017 ; ecosystem/wallet/05).
 * Vit dans le schéma `ledger` déjà partagé par `Wallet\Balance`
 * (`PersonLedgerAccounts` y provisionne déjà des `Account` sans schéma
 * dédié) plutôt que d'ouvrir un nouveau schéma pour un seul sous-module.
 *
 * `amount` est le montant demandé par la personne (parité 1 WP = 1 FCFA,
 * AMD-0003) — la seule source de vérité pour le crédit final
 * (ecosystem/wallet/05 §3) ; `fees`/`net_amount` ne sont connus qu'après
 * réponse GeniusPay et jamais utilisés pour recalculer le montant crédité.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE ledger.wallet_deposits (
                id uuid PRIMARY KEY,
                person_id uuid NOT NULL,
                initiated_by_person_account_link_id uuid NOT NULL,
                state text NOT NULL,
                country_code text NOT NULL,
                currency text NOT NULL,
                amount integer NOT NULL CHECK (amount > 0),
                provider text NOT NULL DEFAULT 'geniuspay',
                provider_payment_id text NULL,
                provider_reference text NULL,
                checkout_url text NULL,
                fees_amount integer NULL CHECK (fees_amount IS NULL OR fees_amount >= 0),
                net_amount integer NULL CHECK (net_amount IS NULL OR net_amount >= 0),
                idempotency_key text NOT NULL,
                ledger_transaction_id uuid NULL REFERENCES ledger.ledger_transactions (id),
                failure_reason text NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT wallet_deposits_idempotency_key_unique UNIQUE (idempotency_key),
                CONSTRAINT wallet_deposits_provider_reference_unique UNIQUE (provider_reference),
                CONSTRAINT wallet_deposits_country_pilot_check CHECK (country_code = 'CI'),
                CONSTRAINT wallet_deposits_provider_pilot_check CHECK (provider = 'geniuspay')
            )
        SQL);

        DB::statement('CREATE INDEX wallet_deposits_person_id_index ON ledger.wallet_deposits (person_id)');
        DB::statement('CREATE INDEX wallet_deposits_state_index ON ledger.wallet_deposits (state)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ledger.wallet_deposits');
    }
};
