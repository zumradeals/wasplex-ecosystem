<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.campaign_fundings` (véto du dirigeant, 2026-07-30) :
 * financement de campagne en libre-service par l'annonceur via GeniusPay,
 * même modèle de preuve que `ledger.wallet_deposits` (AMD-0017) — un crédit
 * n'existe jamais avant confirmation externe signée du prestataire
 * (webhook), jamais par simple déclaration de l'annonceur (ADR-0003 §7-8).
 *
 * Distincte du flux `campaign.fund` existant (staff Wasplex, paiements hors
 * GeniusPay confirmés manuellement) : les deux coexistent, chacun crédite
 * `campaign->available_account_id` via `CampaignBudgetService::fund()`, avec
 * des clés d'idempotence qui ne peuvent jamais entrer en collision (voir
 * `CampaignFundingWebhookService`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.campaign_fundings (
                id uuid PRIMARY KEY,
                campaign_id uuid NOT NULL REFERENCES advertising.campaigns (id),
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
                CONSTRAINT campaign_fundings_idempotency_key_unique UNIQUE (idempotency_key),
                CONSTRAINT campaign_fundings_provider_reference_unique UNIQUE (provider_reference),
                CONSTRAINT campaign_fundings_provider_pilot_check CHECK (provider = 'geniuspay')
            )
        SQL);

        DB::statement('CREATE INDEX campaign_fundings_campaign_id_index ON advertising.campaign_fundings (campaign_id)');
        DB::statement('CREATE INDEX campaign_fundings_state_index ON advertising.campaign_fundings (state)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.campaign_fundings');
    }
};
