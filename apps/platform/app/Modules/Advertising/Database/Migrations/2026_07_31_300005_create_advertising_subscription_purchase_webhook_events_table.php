<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inbox des webhooks GeniusPay pour l'achat d'abonnement, sur le modèle
 * exact de `advertising.advertiser_wallet_deposit_webhook_events`
 * (migration `2026_07_31_100004`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.subscription_purchase_webhook_events (
                id uuid PRIMARY KEY,
                provider text NOT NULL DEFAULT 'geniuspay',
                event_type text NULL,
                signature_valid boolean NOT NULL,
                raw_payload text NOT NULL,
                received_at timestamptz NOT NULL DEFAULT now(),
                processed_at timestamptz NULL,
                processing_result text NULL,
                subscription_purchase_id uuid NULL REFERENCES advertising.subscription_purchases (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX subscription_purchase_webhook_events_unprocessed_index ON advertising.subscription_purchase_webhook_events (processed_at) WHERE processed_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.subscription_purchase_webhook_events');
    }
};
