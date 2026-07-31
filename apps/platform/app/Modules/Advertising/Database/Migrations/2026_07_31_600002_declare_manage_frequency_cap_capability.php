<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertising.manage_frequency_cap` (instruction explicite du
 * fondateur, 2026-07-31) — mirroir exact de
 * `advertising.manage_video_duration_bounds` (migration `2026_07_30_600002`) :
 * contenu de référence, `risk_class = ordinary` / `minimum_session_assurance
 * = weak`, réservée au personnel Wasplex habilité, jamais auto-octroyée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertising.manage_frequency_cap',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'manage_frequency_cap',
            'description' => 'Régler le nombre de revisionnages gratuits (quotidien et total) autorisés pour une même personne sur une même publicité, au-delà de la récompense unique.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertising.manage_frequency_cap')->delete();
    }
};
