<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.advertiser_wallet_deposits` (instruction explicite du
 * fondateur, 2026-07-31) : dépôt en libre-service dans le solde annonceur
 * mutualisé via GeniusPay, même modèle de preuve que
 * `advertising.campaign_fundings` (migration `2026_07_30_300001`) — un
 * crédit n'existe jamais avant confirmation externe signée du prestataire
 * (webhook), jamais par simple déclaration de l'annonceur.
 *
 * Distincte de `advertising.campaign_fundings` : un dépôt Wallet n'est
 * rattaché à aucune campagne au moment où il est reçu — voir
 * `advertising.advertiser_wallet_allocations` pour le rattachement
 * ultérieur, distinct et volontaire, à une campagne précise.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.advertiser_wallet_deposits (
                id uuid PRIMARY KEY,
                advertiser_profile_id uuid NOT NULL REFERENCES advertising.advertiser_profiles (id),
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
                CONSTRAINT advertiser_wallet_deposits_idempotency_key_unique UNIQUE (idempotency_key),
                CONSTRAINT advertiser_wallet_deposits_provider_reference_unique UNIQUE (provider_reference),
                CONSTRAINT advertiser_wallet_deposits_provider_pilot_check CHECK (provider = 'geniuspay')
            )
        SQL);

        DB::statement('CREATE INDEX advertiser_wallet_deposits_advertiser_profile_id_index ON advertising.advertiser_wallet_deposits (advertiser_profile_id)');
        DB::statement('CREATE INDEX advertiser_wallet_deposits_state_index ON advertising.advertiser_wallet_deposits (state)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.advertiser_wallet_deposits');
    }
};
