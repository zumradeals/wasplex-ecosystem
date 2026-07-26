<?php

use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ajoute `campaign_version.like`, `campaign_version.favorite` et
 * `campaign_version.share` au rôle modèle `user.base` (Lot 3 Phase A,
 * décision de Koné 2026-07-26) — même mécanisme générique que
 * `event.self_submit` (migration `2026_07_25_400002`) : toute personne
 * inscrite reçoit ces trois capacités dès l'inscription
 * ({@see GrantAutoIssuer}),
 * sans octroi manuel. C'est le mécanisme réutilisable identifié pour ce
 * lot — aucun nouveau mécanisme de distribution n'est créé.
 *
 * `user.base` est déjà `active` : le catalogue de capacités d'un
 * role_templates actif est figé
 * (`role_template_capabilities_prevent_active_mutation`) — même bascule
 * explicite `draft` → insertion → `active` que la migration de référence.
 */
return new class extends Migration
{
    private const CAPABILITY_KEYS = [
        'campaign_version.like',
        'campaign_version.favorite',
        'campaign_version.share',
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
