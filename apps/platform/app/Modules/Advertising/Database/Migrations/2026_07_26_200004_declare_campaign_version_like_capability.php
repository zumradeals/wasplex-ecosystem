<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign_version.like` (Lot 3 Phase A, menu vertical du Feed,
 * décision de Koné 2026-07-26). Bascule (toggle) un « j'aime » sur
 * n'importe quelle `CampaignVersion` diffusée — jamais réservée au
 * représentant du dossier annonceur visé, sur le même raisonnement de
 * portée que `campaign.report` (migration `2026_07_25_100006`) : un signal
 * social s'exprime sur le contenu de n'importe qui, pas seulement le sien.
 *
 * Portée des grants réels : `resource_type = advertising.campaign_version`
 * sans `resource_ids`, jamais `self` (qui exigerait que le sujet soit
 * lui-même l'annonceur visé — voir `ScopeMatcher`).
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : bascule un état persistant (ligne créée/retirée).
 * - `risk_class = ordinary` : entièrement réversible (re-cliquer retire),
 *   sans aucun effet financier, sans influence sur un prix, un quota ou une
 *   décision d'acceptation d'événement qualifié — un signal social pur, à
 *   l'opposé du profil de `campaign.fund`/`event.accept`.
 * - `purpose_required = false` : une écriture ordinaire, pas un accès
 *   sensible (ADR-0004 §8).
 * - `approval_required = false` : décision entièrement portée par
 *   `SocialEngagementService::toggleLike()`.
 * - `minimum_session_assurance = weak` : même plancher que les autres
 *   capacités auto-émises de `user.base` (TD-0002-A).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign_version.like',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'like',
            'description' => "Basculer un « j'aime » sur une publicité (Lot 3 Phase A). Signal social pur, aucun effet financier.",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign_version.like')->delete();
    }
};
