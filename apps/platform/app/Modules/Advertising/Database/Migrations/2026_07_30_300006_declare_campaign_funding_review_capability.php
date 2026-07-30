<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign_funding.review` (véto du dirigeant, 2026-07-30) : même
 * doctrine que `wallet_deposit.review` (TD-0008-D, migration
 * `2026_07_30_100006`), appliquée au financement de campagne. Couvre
 * exclusivement la consultation, par du personnel Wasplex habilité, des
 * financements en litige (`unknown_reconciliation`) et des webhooks
 * GeniusPay à signature invalide — jamais leur résolution.
 *
 * Mêmes dimensions que `wallet_deposit.review`, même raisonnement exact :
 * `operation=read`, `risk_class=sensitive` (position financière d'un
 * annonceur déterminé consultée par un tiers membre du personnel),
 * `purpose_required=false` (aucune finalité de rapprochement financier
 * versionnée n'est encore catalguée — même écart suivi que TD-0008-D),
 * `approval_required=false` (simple lecture), `minimum_session_assurance=weak`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign_funding.review',
            'version' => 1,
            'domain' => 'campaign_funding',
            'action' => 'review',
            'description' => 'Consulter les financements de campagne en litige (unknown_reconciliation) et les webhooks GeniusPay à signature invalide, pour revue humaine.',
            'operation' => 'read',
            'risk_class' => 'sensitive',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign_funding.review')->delete();
    }
};
