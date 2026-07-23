<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\PolicyState;
use App\Modules\Governance\Authorization\Enums\RoleTemplateState;
use App\Modules\Governance\Authorization\Models\RoleTemplate;
use App\Modules\Governance\Authorization\Models\RoleTemplateCapability;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Immutabilité sémantique des versions actives (P003-B1.1 §4). Un
 * remplacement passe toujours par un état/version explicite, jamais par une
 * réécriture silencieuse.
 */
class SemanticImmutabilityTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_active_capability_refuses_semantic_field_update(): void
    {
        $capability = $this->makeCapability('sample.immutable_capability');

        $this->expectException(QueryException::class);

        $capability->forceFill(['description' => 'Description modifiée après activation.'])->save();
    }

    public function test_active_capability_allows_state_transition_alone(): void
    {
        $capability = $this->makeCapability('sample.retirable_capability');

        $capability->forceFill(['state' => CapabilityState::Retired])->save();

        $this->assertSame(CapabilityState::Retired, $capability->fresh()->state);
    }

    public function test_replacing_a_capability_uses_a_new_version_row(): void
    {
        $v1 = $this->makeCapability('sample.versioned_capability', version: 1);
        $v1->forceFill(['state' => CapabilityState::Retired])->save();

        $v2 = $this->makeCapability('sample.versioned_capability', version: 2);

        $this->assertSame(CapabilityState::Retired, $v1->fresh()->state);
        $this->assertSame(CapabilityState::Active, $v2->fresh()->state);
        $this->assertNotSame($v1->id, $v2->id);
    }

    public function test_capability_definitions_refuses_physical_deletion_once_referenced(): void
    {
        $capability = $this->makeCapability('sample.referenced_capability');
        $link = $this->activeLinkFor($this->makeUser('immut-capability-delete@example.com'));
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $this->expectException(QueryException::class);

        DB::table('governance.capability_definitions')->where('id', $capability->id)->delete();
    }

    public function test_policy_content_and_checksum_are_never_modified(): void
    {
        $policy = $this->makePolicy('sample_immutable_policy');

        $this->expectException(QueryException::class);

        $policy->forceFill(['checksum' => hash('sha256', 'contenu-modifie')])->save();
    }

    public function test_a_referenced_policy_still_refuses_content_mutation(): void
    {
        $capability = $this->makeCapability('sample.policy_referenced');
        $link = $this->activeLinkFor($this->makeUser('immut-policy-referenced@example.com'));
        $policy = $this->makePolicy('sample_referenced_policy');
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        // Même après retrait, une politique déjà référencée par un grant ne
        // peut jamais voir son contenu modifié (P003-B1.1 §4).
        $policy->forceFill(['state' => PolicyState::Retired])->save();

        $this->expectException(QueryException::class);

        $policy->fresh()->forceFill(['checksum' => hash('sha256', 'autre-contenu')])->save();
    }

    public function test_policy_versions_refuses_physical_deletion_once_referenced(): void
    {
        $capability = $this->makeCapability('sample.policy_delete_referenced');
        $link = $this->activeLinkFor($this->makeUser('immut-policy-delete@example.com'));
        $policy = $this->makePolicy('sample_policy_delete_referenced');
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $this->expectException(QueryException::class);

        DB::table('governance.policy_versions')->where('id', $policy->id)->delete();
    }

    public function test_active_role_template_refuses_semantic_field_update(): void
    {
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'immutable_role',
            'version' => 1,
            'label' => 'Rôle immuable',
            'description' => 'Description initiale.',
            'state' => RoleTemplateState::Active,
        ]);

        $this->expectException(QueryException::class);

        $roleTemplate->forceFill(['label' => 'Nouveau libellé'])->save();
    }

    public function test_active_role_template_allows_state_transition_alone(): void
    {
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'retirable_role',
            'version' => 1,
            'label' => 'Rôle retirable',
            'description' => 'Description.',
            'state' => RoleTemplateState::Active,
        ]);

        $roleTemplate->forceFill(['state' => RoleTemplateState::Retired])->save();

        $this->assertSame(RoleTemplateState::Retired, $roleTemplate->fresh()->state);
    }

    public function test_active_role_template_capability_catalog_cannot_be_extended(): void
    {
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'frozen_catalog_role',
            'version' => 1,
            'label' => 'Rôle catalogue figé',
            'description' => 'Description.',
            'state' => RoleTemplateState::Active,
        ]);
        $capability = $this->makeCapability('sample.frozen_catalog');

        $this->expectException(QueryException::class);

        RoleTemplateCapability::create([
            'role_template_id' => $roleTemplate->id,
            'capability_definition_id' => $capability->id,
        ]);
    }

    public function test_active_role_template_capability_catalog_cannot_be_removed(): void
    {
        $capability = $this->makeCapability('sample.frozen_catalog_removal');
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'frozen_catalog_removal_role',
            'version' => 1,
            'label' => 'Rôle catalogue figé (retrait)',
            'description' => 'Description.',
            'state' => RoleTemplateState::Draft,
        ]);
        $link = RoleTemplateCapability::create([
            'role_template_id' => $roleTemplate->id,
            'capability_definition_id' => $capability->id,
        ]);
        $roleTemplate->update(['state' => RoleTemplateState::Active]);

        $this->expectException(QueryException::class);

        DB::table('governance.role_template_capabilities')->where('id', $link->id)->delete();
    }

    public function test_draft_role_template_capability_catalog_can_still_be_composed(): void
    {
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'draft_catalog_role',
            'version' => 1,
            'label' => 'Rôle en préparation',
            'description' => 'Description.',
            'state' => RoleTemplateState::Draft,
        ]);
        $capability = $this->makeCapability('sample.draft_catalog');

        $link = RoleTemplateCapability::create([
            'role_template_id' => $roleTemplate->id,
            'capability_definition_id' => $capability->id,
        ]);

        $this->assertTrue(RoleTemplateCapability::query()->whereKey($link->id)->exists());
    }

    public function test_role_templates_refuses_physical_deletion_once_referenced(): void
    {
        $capability = $this->makeCapability('sample.role_delete_referenced');
        $link = $this->activeLinkFor($this->makeUser('immut-role-delete@example.com'));
        $policy = $this->makePolicy();
        $roleTemplate = RoleTemplate::create([
            'stable_key' => 'referenced_for_deletion_role',
            'version' => 1,
            'label' => 'Rôle référencé',
            'description' => 'Description.',
            'state' => RoleTemplateState::Draft,
        ]);
        RoleTemplateCapability::create([
            'role_template_id' => $roleTemplate->id,
            'capability_definition_id' => $capability->id,
        ]);
        $roleTemplate->update(['state' => RoleTemplateState::Active]);

        // La référence au rôle modèle est posée dès la proposition : après
        // création, role_template_id n'est plus modifiable (P003-B1.3 §4).
        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            roleTemplate: $roleTemplate,
        );

        $this->expectException(QueryException::class);

        DB::table('governance.role_templates')->where('id', $roleTemplate->id)->delete();
    }
}
