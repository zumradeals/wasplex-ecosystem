<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inbox des webhooks GeniusPay pour le financement de campagne, sur le
 * modèle exact de `ledger.wallet_deposit_webhook_events` (migration
 * `2026_07_30_100002`, ADR-0007 §11). Toute réception est enregistrée ici —
 * signature valide ou non — avant toute tentative de traitement métier.
 *
 * Table distincte de `ledger.wallet_deposit_webhook_events` (frontière de
 * module : Advertising ne possède jamais une ligne du Wallet, et
 * inversement) — voir `App\Http\Controllers\GeniusPayWebhookController` pour
 * la répartition entre les deux depuis la même URL de webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.campaign_funding_webhook_events (
                id uuid PRIMARY KEY,
                provider text NOT NULL DEFAULT 'geniuspay',
                event_type text NULL,
                signature_valid boolean NOT NULL,
                raw_payload text NOT NULL,
                received_at timestamptz NOT NULL DEFAULT now(),
                processed_at timestamptz NULL,
                processing_result text NULL,
                campaign_funding_id uuid NULL REFERENCES advertising.campaign_fundings (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX campaign_funding_webhook_events_unprocessed_index ON advertising.campaign_funding_webhook_events (processed_at) WHERE processed_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.campaign_funding_webhook_events');
    }
};
