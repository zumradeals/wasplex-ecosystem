<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign_version.share` (Lot 3 Phase A, décision de Koné
 * 2026-07-26) : enregistre une intention de partage (le partage effectif a
 * lieu hors plateforme — aucun destinataire ni canal tracé, cohérent
 * AMD-0001/AMD-0009, voir `advertising.campaign_version_shares`).
 *
 * Portée identique à `campaign_version.like`/`campaign_version.favorite` :
 * `resource_type = advertising.campaign_version`, jamais `self`.
 *
 * - `operation = write` : crée une ligne d'événement (jamais un toggle,
 *   contrairement au like/favori — partager plusieurs fois est légitime).
 * - `risk_class = ordinary` : aucun effet financier, aucune donnée
 *   personnelle de tiers collectée.
 * - `purpose_required = false`, `approval_required = false`,
 *   `minimum_session_assurance = weak` : même raisonnement que
 *   `campaign_version.like`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign_version.share',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'share',
            'description' => 'Enregistrer une intention de partage sur une publicité (Lot 3 Phase A). Aucun destinataire ni canal tracé.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign_version.share')->delete();
    }
};
