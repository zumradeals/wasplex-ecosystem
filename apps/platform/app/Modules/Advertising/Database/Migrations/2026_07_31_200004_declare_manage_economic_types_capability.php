<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertising.manage_economic_types` (instruction explicite du
 * fondateur, 2026-07-31) — mirroir exact de
 * `advertising.manage_sector_classifications` (migration `2026_07_30_700001`)
 * : contenu de référence administrable, `risk_class = ordinary` /
 * `minimum_session_assurance = weak`, réservée au personnel Wasplex
 * habilité, jamais auto-octroyée.
 *
 * `risk_class = ordinary` malgré l'impact financier indirect (le
 * pourcentage module la part utilisateur réelle) : cette capacité ne
 * bouge elle-même aucune valeur Ledger — elle publie une configuration,
 * lue ensuite par `CampaignBudgetService::acceptQualifiedEvent()` au
 * moment réel de chaque événement. Même raisonnement que
 * `advertising.qualified_event_base_price`
 * (`ConfigurationResolver`, gouvernée séparément par ADR-0002).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertising.manage_economic_types',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'manage_economic_types',
            'description' => 'Publier une nouvelle version des trois types économiques — nom, pourcentage de la part utilisateur, quota mensuel, type par défaut.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertising.manage_economic_types')->delete();
    }
};
