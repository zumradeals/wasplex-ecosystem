<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes partiels des deux files de supervision P010-B. Ils bornent le
 * travail PostgreSQL aux seules anomalies réellement affichées et suivent
 * exactement l'ordre stable utilisé par AdminWalletDepositController.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE INDEX wallet_deposits_unknown_review_index
            ON ledger.wallet_deposits (created_at, id)
            WHERE state = 'unknown_reconciliation'
        SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX wallet_webhooks_invalid_review_index
            ON ledger.wallet_deposit_webhook_events (received_at, id)
            WHERE signature_valid = false
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ledger.wallet_webhooks_invalid_review_index');
        DB::statement('DROP INDEX IF EXISTS ledger.wallet_deposits_unknown_review_index');
    }
};
