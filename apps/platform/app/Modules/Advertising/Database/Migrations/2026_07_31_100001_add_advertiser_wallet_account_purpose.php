<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étend la liste fermée de `ledger.accounts.purpose` (migration
 * `2026_07_23_200002`) avec `advertiser_wallet` (instruction explicite du
 * fondateur, 2026-07-31) : solde annonceur mutualisé, distinct du
 * compartiment par campagne (`advertiser_campaign`) — un dépôt libre-service
 * n'est plus rattaché à une campagne au moment où il est reçu, seule
 * l'allocation ultérieure (transfert interne Ledger, jamais une nouvelle
 * couverture) le rattache à une campagne précise.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ledger.accounts DROP CONSTRAINT accounts_purpose_check');

        DB::statement(
            'ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_purpose_check CHECK (purpose IN ('
            ."'coverage', 'advertiser_campaign', 'advertiser_wallet', 'user_rights', 'wasplex_own_resources', "
            ."'social_fund', 'cards_pool', 'tax_and_fees', 'transit_payment', 'clearing'"
            .'))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledger.accounts DROP CONSTRAINT accounts_purpose_check');

        DB::statement(
            'ALTER TABLE ledger.accounts ADD CONSTRAINT accounts_purpose_check CHECK (purpose IN ('
            ."'coverage', 'advertiser_campaign', 'user_rights', 'wasplex_own_resources', "
            ."'social_fund', 'cards_pool', 'tax_and_fees', 'transit_payment', 'clearing'"
            .'))'
        );
    }
};
