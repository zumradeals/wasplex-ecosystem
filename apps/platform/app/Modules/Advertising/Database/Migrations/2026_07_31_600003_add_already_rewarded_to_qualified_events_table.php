<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute `already_rewarded` à `advertising.qualified_events` (instruction
 * explicite du fondateur, 2026-07-31 : « récompensé une seule fois pour
 * une pub déjà vue, c'est automatique »). Distinct de `quota_exceeded`
 * (quota mensuel personnel) et `economic_type_pool_exhausted` (cagnotte
 * de campagne épuisée) : cette colonne trace une troisième raison,
 * indépendante des deux autres, pour laquelle un événement peut être
 * accepté avec `user_share_amount = 0` — la personne a déjà été
 * récompensée pour CETTE `CampaignVersion` précise par un événement
 * antérieur déjà accepté. Wasplex absorbe alors l'intégralité du
 * montant (décision explicite du fondateur, 2026-07-31) : un
 * revisionnage gratuit reste une exposition réelle pour l'annonceur,
 * qui continue de la payer normalement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE advertising.qualified_events
                ADD COLUMN already_rewarded boolean NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE advertising.qualified_events
                DROP COLUMN IF EXISTS already_rewarded
        SQL);
    }
};
