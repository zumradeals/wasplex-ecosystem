<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inbox des webhooks GeniusPay (ADR-0007 §11 : « Wasplex vérifie avant
 * tout effet. Il enregistre durablement l'entrée dans une inbox, puis
 * répond rapidement. Le traitement métier est asynchrone et idempotent. »).
 * Toute réception est enregistrée ici — signature valide ou non — avant
 * toute tentative de traitement métier.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE ledger.wallet_deposit_webhook_events (
                id uuid PRIMARY KEY,
                provider text NOT NULL DEFAULT 'geniuspay',
                event_type text NULL,
                signature_valid boolean NOT NULL,
                raw_payload text NOT NULL,
                received_at timestamptz NOT NULL DEFAULT now(),
                processed_at timestamptz NULL,
                processing_result text NULL,
                wallet_deposit_id uuid NULL REFERENCES ledger.wallet_deposits (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX wallet_deposit_webhook_events_unprocessed_index ON ledger.wallet_deposit_webhook_events (processed_at) WHERE processed_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ledger.wallet_deposit_webhook_events');
    }
};
