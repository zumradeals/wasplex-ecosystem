<?php

use App\Modules\Advertising\Http\Controllers\AdvertisingOverviewController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.view` (P007) — première capacité de lecture du module
 * Publicité. Couvre exclusivement la consultation, par un représentant
 * annonceur, de ses propres campagnes et de leur budget projeté
 * ({@see AdvertisingOverviewController}) —
 * jamais les campagnes d'un autre dossier annonceur.
 *
 * Portée des grants réels — exclusivement `self`
 * (`ResourceContext.ownerPersonId` doit être le représentant réellement
 * persisté du dossier annonceur, jamais une prétention transmise par le
 * client) : même construction que `campaign.create`.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = read` : première capacité de lecture de ce module — même
 *   raisonnement que `wallet.view` (migration `2026_07_25_200001`).
 * - `risk_class = ordinary` : consulter ses propres campagnes n'expose
 *   aucune donnée d'autrui et n'a aucun effet.
 * - `purpose_required = false` : consultation de sa propre ressource,
 *   déjà strictement scopée `self` (ADR-0004 §8).
 * - `approval_required = false` : simple lecture de projection.
 * - `minimum_session_assurance = weak` : même plancher que `wallet.view`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.view',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'view',
            'description' => 'Consulter ses propres campagnes et leur budget projeté (ADR-0010 §3).',
            'operation' => 'read',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.view')->delete();
    }
};
