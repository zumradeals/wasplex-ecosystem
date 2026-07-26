<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign_version.favorite` (Lot 3 Phase A, décision de Koné
 * 2026-07-26) — même raisonnement exact que `campaign_version.like`
 * (migration `2026_07_26_200004`) : bascule (toggle), portée
 * `resource_type = advertising.campaign_version`, `risk_class = ordinary`,
 * aucun effet financier.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign_version.favorite',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'favorite',
            'description' => 'Basculer un favori sur une publicité (Lot 3 Phase A). Signal social pur, aucun effet financier.',
            'operation' => 'write',
            'risk_class' => 'ordinary',
            'purpose_required' => false,
            'approval_required' => false,
            'minimum_session_assurance' => 'weak',
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign_version.favorite')->delete();
    }
};
