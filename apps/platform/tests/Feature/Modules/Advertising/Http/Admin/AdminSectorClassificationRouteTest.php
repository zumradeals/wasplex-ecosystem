<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Gestion admin de la matrice de classification des secteurs (Lot 9, véto
 * du dirigeant 2026-07-30) : gouvernée par
 * `advertising.manage_sector_classifications` — mirroir de
 * `AdminVideoDurationRouteTest`/`AdminInterestTaxonomyRouteTest`, adapté
 * au versionnage par (country_code, sector) plutôt qu'une seule ligne
 * active globale.
 */
class AdminSectorClassificationRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'country_code' => 'SN',
            'sector' => 'alimentation',
            'sector_class' => 'standard_authorization',
            'minimum_age' => null,
            'required_evidence' => [],
            'warnings' => [],
            'allowed_formats' => ['banner'],
            'allowed_targeting' => ['country'],
            'review_level' => 'standard',
            'minimum_approvals' => 1,
        ], $overrides);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/sector-classifications');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/sector-classifications');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/sector-classifications')
            ->where('access.allowed', false)
            ->where('classifications', []),
        );
    }

    public function test_a_holder_of_the_capability_can_publish_a_new_classification(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_sector_classifications', 'advertising.sector_classification');

        $response = $this->actingAs($staff->user)->postJson('/admin/sector-classifications', $this->validPayload());

        $response->assertCreated();
        $response->assertJson([
            'country_code' => 'SN',
            'sector' => 'alimentation',
            'version' => 1,
            'state' => 'active',
        ]);
        $this->assertDatabaseHas('advertising.sector_classifications', [
            'country_code' => 'SN',
            'sector' => 'alimentation',
            'version' => 1,
            'state' => 'active',
        ]);
    }

    public function test_publishing_a_new_version_retires_only_the_same_pair(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_sector_classifications', 'advertising.sector_classification');

        $untouched = $this->createActiveClassification('sante');

        $this->actingAs($staff->user)->postJson('/admin/sector-classifications', $this->validPayload([
            'country_code' => $untouched->country_code,
            'sector' => 'alimentation',
        ]))->assertCreated();

        $response = $this->actingAs($staff->user)->postJson('/admin/sector-classifications', $this->validPayload([
            'country_code' => $untouched->country_code,
            'sector' => 'alimentation',
            'minimum_age' => 21,
        ]));

        $response->assertCreated();
        $response->assertJson(['version' => 2, 'minimum_age' => 21]);

        $this->assertDatabaseHas('advertising.sector_classifications', [
            'country_code' => $untouched->country_code,
            'sector' => 'alimentation',
            'version' => 1,
            'state' => 'retired',
        ]);
        $this->assertDatabaseHas('advertising.sector_classifications', [
            'country_code' => $untouched->country_code,
            'sector' => 'alimentation',
            'version' => 2,
            'state' => 'active',
        ]);
        // La paire non concernée reste active, jamais touchée.
        $this->assertDatabaseHas('advertising.sector_classifications', [
            'id' => $untouched->id,
            'sector' => 'sante',
            'state' => 'active',
        ]);
    }

    public function test_a_forbidden_sector_class_is_rejected(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_sector_classifications', 'advertising.sector_classification');

        $response = $this->actingAs($staff->user)->postJson('/admin/sector-classifications', $this->validPayload([
            'sector_class' => 'forbidden',
        ]));

        $response->assertStatus(422);
        $this->assertDatabaseMissing('advertising.sector_classifications', ['sector_class' => 'forbidden']);
    }

    public function test_a_subject_without_the_capability_cannot_publish(): void
    {
        $user = $this->makeRepresentative();

        $response = $this->actingAs($user->user)->postJson('/admin/sector-classifications', $this->validPayload());

        $response->assertStatus(403);
        $this->assertDatabaseCount('advertising.sector_classifications', 0);
    }

    public function test_a_holder_of_the_capability_can_retire_without_replacement(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'advertising.manage_sector_classifications', 'advertising.sector_classification');
        $classification = $this->createActiveClassification('alimentation');

        $response = $this->actingAs($staff->user)->postJson("/admin/sector-classifications/{$classification->id}/retire", []);

        $response->assertOk();
        $response->assertJson(['state' => 'retired']);
        $this->assertDatabaseHas('advertising.sector_classifications', [
            'id' => $classification->id,
            'state' => 'retired',
        ]);
    }

    public function test_the_admin_sector_classification_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.sector-classifications'));
        $this->assertTrue(Route::has('admin.sector-classifications.store'));
        $this->assertTrue(Route::has('admin.sector-classifications.retire'));
    }

    private function createActiveClassification(string $sector): SectorClassification
    {
        return SectorClassification::create([
            'country_code' => 'CI',
            'sector' => $sector,
            'version' => 1,
            'sector_class' => 'standard_authorization',
            'minimum_age' => null,
            'required_evidence' => [],
            'warnings' => [],
            'allowed_formats' => ['banner'],
            'allowed_targeting' => ['country'],
            'frequency_rules' => [],
            'review_level' => 'standard',
            'minimum_approvals' => 1,
            'state' => 'active',
        ]);
    }
}
