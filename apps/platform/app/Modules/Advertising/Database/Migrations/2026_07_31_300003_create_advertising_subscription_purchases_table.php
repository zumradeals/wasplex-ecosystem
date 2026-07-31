<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.subscription_purchases` (instruction explicite du
 * fondateur, 2026-07-31) : achat d'un plan d'abonnement via GeniusPay,
 * même modèle de preuve que `advertising.advertiser_wallet_deposits`
 * (migration `2026_07_31_100002`) — un abonnement n'est jamais activé
 * avant confirmation externe signée du prestataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.subscription_purchases (
                id uuid PRIMARY KEY,
                person_id uuid NOT NULL REFERENCES identity.people (id),
                subscription_plan_id uuid NOT NULL REFERENCES advertising.subscription_plans (id),
                initiated_by_person_account_link_id uuid NOT NULL,
                state text NOT NULL,
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
                CONSTRAINT subscription_purchases_idempotency_key_unique UNIQUE (idempotency_key),
                CONSTRAINT subscription_purchases_provider_reference_unique UNIQUE (provider_reference),
                CONSTRAINT subscription_purchases_provider_pilot_check CHECK (provider = 'geniuspay')
            )
        SQL);

        DB::statement('CREATE INDEX subscription_purchases_person_id_index ON advertising.subscription_purchases (person_id)');
        DB::statement('CREATE INDEX subscription_purchases_state_index ON advertising.subscription_purchases (state)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.subscription_purchases');
    }
};
