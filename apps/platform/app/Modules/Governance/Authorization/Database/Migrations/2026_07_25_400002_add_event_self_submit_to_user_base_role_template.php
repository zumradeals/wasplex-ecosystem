<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajoute `event.self_submit` au rôle modèle `user.base` (W2) — toute
 * personne inscrite peut désormais soumettre sa propre preuve d'attention
 * qualifiée dès l'inscription, sur le même principe que `wallet.view`,
 * `advertiser_profile.create` et `campaign.view` (migration
 * `2026_07_25_200002`).
 *
 * `user.base` est déjà `active` en production : le catalogue de
 * capacités d'un role_templates actif est figé
 * (`role_template_capabilities_prevent_active_mutation`, P003-B1.1 §4).
 * Cette migration bascule donc explicitement `draft` → insertion →
 * `active`, plutôt que d'éditer la migration `2026_07_25_200002` déjà
 * exécutée en production — même discipline que toute évolution d'un
 * rôle modèle après son activation initiale.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleTemplateId = DB::table('governance.role_templates')
            ->where('stable_key', 'user.base')
            ->where('state', 'active')
            ->value('id');

        $capabilityId = DB::table('governance.capability_definitions')
            ->where('stable_key', 'event.self_submit')
            ->where('state', 'active')
            ->value('id');

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'draft']);

        DB::table('governance.role_template_capabilities')->insert([
            'id' => (string) Str::uuid7(),
            'role_template_id' => $roleTemplateId,
            'capability_definition_id' => $capabilityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'active']);
    }

    public function down(): void
    {
        $roleTemplateId = DB::table('governance.role_templates')
            ->where('stable_key', 'user.base')
            ->where('state', 'active')
            ->value('id');

        $capabilityId = DB::table('governance.capability_definitions')
            ->where('stable_key', 'event.self_submit')
            ->value('id');

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'draft']);

        DB::table('governance.role_template_capabilities')
            ->where('role_template_id', $roleTemplateId)
            ->where('capability_definition_id', $capabilityId)
            ->delete();

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'active']);
    }
};
