<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `subscription.purchase` (instruction explicite du fondateur,
 * 2026-07-31) : achat en libre-service d'un abonnement via GeniusPay —
 * mêmes dimensions et même raisonnement que `advertiser_wallet.deposit`
 * (migration `2026_07_31_100005`), portée `self` : une personne n'achète
 * jamais un abonnement pour une autre.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'subscription.purchase',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'purchase',
            'description' => 'Acheter un abonnement pour son propre compte via GeniusPay, en libre-service.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'subscription.purchase')->delete();
    }
};
