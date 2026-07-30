<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertising.manage_video_duration_bounds` (Lot 4, instruction
 * explicite du fondateur 2026-07-30) — mirroir exact de
 * `advertising.manage_interest_taxonomy` (migration `2026_07_30_400003`) :
 * contenu de référence, pas de mouvement financier ni de secret,
 * `risk_class = ordinary` / `minimum_session_assurance = weak`. Portée
 * `resource_type` seule, réservée au personnel Wasplex habilité, jamais
 * auto-octroyée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertising.manage_video_duration_bounds',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'manage_video_duration_bounds',
            'description' => 'Régler les bornes de durée (secondes) autorisées pour une vidéo publicitaire jointe à une création de campagne.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertising.manage_video_duration_bounds')->delete();
    }
};
