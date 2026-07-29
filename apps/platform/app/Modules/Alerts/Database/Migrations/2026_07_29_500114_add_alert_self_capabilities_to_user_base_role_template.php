<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajoute `alert_case.submit`, `alert_case.view_self` et
 * `alert_match.propose` au rôle modèle `user.base` (P008-A) — même
 * mécanisme générique que `event.self_submit`/les capacités sociales
 * (migrations `2026_07_25_400002`, `2026_07_26_200007`) : toute personne
 * inscrite reçoit ces trois capacités dès l'inscription
 * (`GrantAutoIssuer`), sans octroi manuel. Les capacités institutionnelles
 * (`alert_case.receive`, `.acknowledge`, `.accept`...) et de modération
 * (`alert_case.review`, `.publish`, `alert_match.validate`,
 * `alert_return.verify`) restent hors de `user.base` — elles exigent un
 * octroi explicite, jamais automatique.
 */
return new class extends Migration
{
    private const CAPABILITY_KEYS = [
        'alert_case.submit',
        'alert_case.view_self',
        'alert_match.propose',
    ];

    public function up(): void
    {
        $roleTemplateId = DB::table('governance.role_templates')
            ->where('stable_key', 'user.base')
            ->where('state', 'active')
            ->value('id');

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'draft']);

        foreach (self::CAPABILITY_KEYS as $capabilityKey) {
            $capabilityId = DB::table('governance.capability_definitions')
                ->where('stable_key', $capabilityKey)
                ->where('state', 'active')
                ->value('id');

            DB::table('governance.role_template_capabilities')->insert([
                'id' => (string) Str::uuid7(),
                'role_template_id' => $roleTemplateId,
                'capability_definition_id' => $capabilityId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'active']);
    }

    public function down(): void
    {
        $roleTemplateId = DB::table('governance.role_templates')
            ->where('stable_key', 'user.base')
            ->where('state', 'active')
            ->value('id');

        $capabilityIds = DB::table('governance.capability_definitions')
            ->whereIn('stable_key', self::CAPABILITY_KEYS)
            ->pluck('id');

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'draft']);

        DB::table('governance.role_template_capabilities')
            ->where('role_template_id', $roleTemplateId)
            ->whereIn('capability_definition_id', $capabilityIds)
            ->delete();

        DB::table('governance.role_templates')->where('id', $roleTemplateId)->update(['state' => 'active']);
    }
};
