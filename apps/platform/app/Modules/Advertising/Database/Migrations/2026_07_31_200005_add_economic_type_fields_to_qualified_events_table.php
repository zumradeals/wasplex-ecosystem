<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute la traçabilité du type économique à `advertising.qualified_events`
 * (instruction explicite du fondateur, 2026-07-31). Le montant réellement
 * crédité au bénéficiaire dépend désormais de son type économique au
 * moment de l'acceptation et de son quota mensuel déjà consommé — deux
 * facteurs qui peuvent changer dans le temps (docs/02 §6 : « les événements
 * passés ne sont pas recalculés »). Persister le montant et le pourcentage
 * réellement appliqués évite toute dérive de recalcul a posteriori — même
 * discipline que `pricing_configuration_key`/`pricing_configuration_version`
 * déjà épinglés sur cette table (migration `2026_07_24_100008`).
 *
 * Toutes les colonnes restent NULL tant que l'événement n'est pas
 * `accepted` (`billing_status = 'pending'`) — jamais une valeur devinée
 * avant l'acceptation réelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE advertising.qualified_events
                ADD COLUMN user_share_amount integer NULL CHECK (user_share_amount IS NULL OR user_share_amount >= 0),
                ADD COLUMN economic_type_id uuid NULL REFERENCES advertising.economic_types (id),
                ADD COLUMN economic_type_percentage_applied integer NULL CHECK (economic_type_percentage_applied IS NULL OR economic_type_percentage_applied BETWEEN 0 AND 100),
                ADD COLUMN quota_exceeded boolean NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE advertising.qualified_events
                DROP COLUMN IF EXISTS user_share_amount,
                DROP COLUMN IF EXISTS economic_type_id,
                DROP COLUMN IF EXISTS economic_type_percentage_applied,
                DROP COLUMN IF EXISTS quota_exceeded
        SQL);
    }
};
