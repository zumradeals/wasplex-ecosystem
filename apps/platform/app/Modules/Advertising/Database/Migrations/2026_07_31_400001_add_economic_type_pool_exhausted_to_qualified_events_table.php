<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute `economic_type_pool_exhausted` à `advertising.qualified_events`
 * (instruction explicite du fondateur, 2026-07-31, confirmée par exemple
 * concret Orange CI). La part utilisateur d'un type économique n'est plus
 * un facteur appliqué à chaque événement : c'est une cagnotte fixe,
 * propre à chaque campagne, dimensionnée en pourcentage de la part
 * utilisateur totale de cette campagne (ex. gratuit 10 %, premium 25 %,
 * gold 30 %, platinum 35 %, totalisant 100 % de la part utilisateur).
 * Chaque spectateur d'un type précis puise dans la cagnotte de son type,
 * au tarif plein (aucune réduction par événement), jusqu'à épuisement.
 *
 * `quota_exceeded` (migration `2026_07_31_200005`) reste le quota
 * personnel mensuel du bénéficiaire — cause distincte d'un versement nul,
 * qui mérite sa propre colonne pour ne jamais confondre les deux raisons
 * dans un audit (« pourquoi cette personne n'a rien reçu ici ? »).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE advertising.qualified_events
                ADD COLUMN economic_type_pool_exhausted boolean NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE advertising.qualified_events
                DROP COLUMN IF EXISTS economic_type_pool_exhausted
        SQL);
    }
};
