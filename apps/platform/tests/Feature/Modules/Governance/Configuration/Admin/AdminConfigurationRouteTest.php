<?php

namespace Tests\Feature\Modules\Governance\Configuration\Admin;

use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

/**
 * Configurations (UX-0001 §8) : gouverné par `configuration.view`,
 * lecture seule (voir `AdminConfigurationController`).
 */
class AdminConfigurationRouteTest extends TestCase
{
    use RefreshDatabase;
    use StaffCapabilityTesting;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/configurations');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/configurations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/configurations')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_active_grant')
            ->where('definitions', []),
        );
    }

    public function test_a_holder_of_configuration_view_sees_the_registry_empty_by_default(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'configuration.view', 'governance.configuration_definition');

        $response = $this->actingAs($staff->user)->get('/admin/configurations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/configurations')
            ->where('access.allowed', true)
            ->where('definitions', []),
        );
    }

    public function test_a_holder_of_configuration_view_sees_a_definition_with_its_full_value_version_history(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'configuration.view', 'governance.configuration_definition');

        $definition = Definition::create([
            'stable_key' => 'advertising.qualified_event.reward_ratio',
            'version' => 1,
            'domain' => 'advertising',
            'level' => ConfigurationLevel::C2,
            'value_type' => ValueType::Integer,
            'unit' => 'pourcent',
            'constraints' => [],
            'description' => 'Ratio de partage utilisateur/Wasplex sur un événement qualifié accepté.',
            'state' => DefinitionState::Active,
        ]);

        $author = $this->makeRepresentative();
        $approver = $this->makeRepresentative();

        $manager = app(ConfigurationValueManager::class);
        $version = $manager->propose($definition, 50, 'Valeur constitutionnelle AMD-0002.', $author);
        $version = $manager->submitForReview($version);
        $version = $manager->approve($version, $approver, 'Conforme à AMD-0002.');
        $manager->activate($version, $approver, (string) Str::uuid());

        $response = $this->actingAs($staff->user)->get('/admin/configurations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/configurations')
            ->where('access.allowed', true)
            ->has('definitions', 1)
            ->where('definitions.0.stable_key', 'advertising.qualified_event.reward_ratio')
            ->where('definitions.0.level', 'c2')
            ->has('definitions.0.value_versions', 1)
            ->where('definitions.0.value_versions.0.value', 50)
            ->where('definitions.0.value_versions.0.state', 'active')
            ->has('definitions.0.value_versions.0.approvals', 1)
            ->where('definitions.0.value_versions.0.approvals.0.decision', 'approved')
            ->where('definitions.0.value_versions.0.activation.activated_by_name', $approver->user->name),
        );
    }

    public function test_the_admin_configurations_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.configurations'));
    }
}
