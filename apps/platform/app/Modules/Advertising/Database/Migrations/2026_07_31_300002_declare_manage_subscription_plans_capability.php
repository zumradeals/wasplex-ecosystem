<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertising.manage_subscription_plans` (instruction explicite du
 * fondateur, 2026-07-31) — mirroir exact de
 * `advertising.manage_economic_types` (migration `2026_07_31_200004`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertising.manage_subscription_plans',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'manage_subscription_plans',
            'description' => 'Publier une nouvelle version des plans d\'abonnement — nom, prix, durée, type économique rattaché.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertising.manage_subscription_plans')->delete();
    }
};
