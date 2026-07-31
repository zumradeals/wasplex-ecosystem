<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Insère l'unique type économique par défaut (instruction explicite du
 * fondateur, 2026-07-31). `user_share_percentage = 100` n'est PAS une
 * valeur de démonstration inventée : c'est la valeur neutre qui reproduit
 * exactement le comportement déjà en vigueur avant ce lot (partage 50/50
 * intégral, sans modulation par type) — insérée par migration, pas par
 * seeder, précisément parce qu'elle doit exister dans TOUT environnement
 * (y compris les tests via `RefreshDatabase`) pour que
 * `CampaignBudgetService::acceptQualifiedEvent()` puisse toujours résoudre
 * un type pour un bénéficiaire sans affectation explicite
 * (`EconomicTypeResolver`, échec fermé sinon).
 *
 * `name` reste un texte administrable ordinaire (docs/02 §3) — « Standard »
 * ici n'est qu'un point de départ, jamais un nom imposé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('advertising.economic_types')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'standard',
            'name' => 'Standard',
            'version' => 1,
            'user_share_percentage' => 100,
            'monthly_quota' => null,
            'is_default' => true,
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('advertising.economic_types')->where('stable_key', 'standard')->delete();
    }
};
