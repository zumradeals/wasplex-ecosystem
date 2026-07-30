<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajoute `wallet.deposit` au rôle modèle `user.base` (AMD-0017) — même
 * mécanisme générique que `wallet.view`/`campaign.create` : toute personne
 * inscrite reçoit cette capacité dès l'inscription (`GrantAutoIssuer`),
 * sans octroi manuel.
 */
return new class extends Migration
{
    private const CAPABILITY_KEY = 'wallet.deposit';

    public function up(): void
    {
        $roleTemplateId = DB::table('governance.role_templates')
            ->where('stable_key', 'user.base')
            ->where('state', 'active')
            ->value('id');

        $capabilityId = DB::table('governance.capability_definitions')
            ->where('stable_key', self::CAPABILITY_KEY)
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
            ->where('stable_key', self::CAPABILITY_KEY)
            ->value('id');

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'draft']);

        DB::table('governance.role_template_capabilities')
            ->where('role_template_id', $roleTemplateId)
            ->where('capability_definition_id', $capabilityId)
            ->delete();

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'active']);
    }
};
