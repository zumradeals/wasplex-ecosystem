<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inbox des webhooks GeniusPay pour le dépôt Wallet annonceur, sur le modèle
 * exact de `advertising.campaign_funding_webhook_events` (migration
 * `2026_07_30_300003`). Toute réception est enregistrée ici — signature
 * valide ou non — avant toute tentative de traitement métier.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.advertiser_wallet_deposit_webhook_events (
                id uuid PRIMARY KEY,
                provider text NOT NULL DEFAULT 'geniuspay',
                event_type text NULL,
                signature_valid boolean NOT NULL,
                raw_payload text NOT NULL,
                received_at timestamptz NOT NULL DEFAULT now(),
                processed_at timestamptz NULL,
                processing_result text NULL,
                advertiser_wallet_deposit_id uuid NULL REFERENCES advertising.advertiser_wallet_deposits (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX advertiser_wallet_deposit_webhook_events_unprocessed_index ON advertising.advertiser_wallet_deposit_webhook_events (processed_at) WHERE processed_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.advertiser_wallet_deposit_webhook_events');
    }
};
