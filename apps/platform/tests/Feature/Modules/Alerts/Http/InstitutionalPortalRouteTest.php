<?php

namespace Tests\Feature\Modules\Alerts\Http;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\DispatchState;
use App\Modules\Alerts\Services\CaseDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class InstitutionalPortalRouteTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/institutions/alerts');

        $response->assertRedirect('/login');
    }

    public function test_a_user_without_institutional_membership_sees_the_denied_state(): void
    {
        $user = $this->makeUser('nobody-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->get('/institutions/alerts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('institutions/alerts/index')
            ->where('access.allowed', false)
            ->where('access.reason', 'no_institutional_membership'),
        );
    }

    public function test_an_institutional_user_sees_only_their_organizations_dispatches(): void
    {
        $institution = $this->makeInstitution();
        $staff = $this->makeRepresentative();
        $this->grantInstitutionalCapability($staff, 'alert_case.receive', $institution, [CaseCategory::Fire]);
        $this->grantInstitutionalCapability($staff, 'alert_case.acknowledge', $institution);

        $case = $this->makeSosCase(category: CaseCategory::Fire);
        $dispatchService = app(CaseDispatchService::class);
        $dispatchService->createDispatch($case, $institution, (string) Str::uuid());
        $dispatchService->transmitPending();

        $otherInstitution = $this->makeInstitution();
        $otherCase = $this->makeSosCase(category: CaseCategory::Fire);
        $dispatchService->createDispatch($otherCase, $otherInstitution, (string) Str::uuid());

        $response = $this->actingAs($staff->user)->get('/institutions/alerts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('institutions/alerts/index')
            ->where('access.allowed', true)
            ->has('dispatches', 1)
            ->where('dispatches.0.state', DispatchState::Transmitted->value)
            ->has('capabilities', 1)
            ->where('capabilities.0', 'alert_case.acknowledge'),
        );
    }

    public function test_an_action_without_the_matching_capability_is_denied(): void
    {
        $institution = $this->makeInstitution();
        $staff = $this->makeRepresentative();
        $this->grantInstitutionalCapability($staff, 'alert_case.receive', $institution, [CaseCategory::Fire]);

        $case = $this->makeSosCase(category: CaseCategory::Fire);
        $dispatchService = app(CaseDispatchService::class);
        $dispatch = $dispatchService->createDispatch($case, $institution, (string) Str::uuid());
        $dispatchService->transmitPending();

        $response = $this->actingAs($staff->user)->postJson(
            "/institutions/alerts/dispatches/{$dispatch->id}/decisions",
            ['decision' => 'acknowledge'],
        );

        $response->assertStatus(403);
    }

    public function test_an_authorized_action_transitions_the_dispatch(): void
    {
        $institution = $this->makeInstitution();
        $staff = $this->makeRepresentative();
        $this->grantInstitutionalCapability($staff, 'alert_case.receive', $institution, [CaseCategory::Fire]);
        $this->grantInstitutionalCapability($staff, 'alert_case.acknowledge', $institution);

        $case = $this->makeSosCase(category: CaseCategory::Fire);
        $dispatchService = app(CaseDispatchService::class);
        $dispatch = $dispatchService->createDispatch($case, $institution, (string) Str::uuid());
        $dispatchService->transmitPending();

        $response = $this->actingAs($staff->user)->postJson(
            "/institutions/alerts/dispatches/{$dispatch->id}/decisions",
            ['decision' => 'acknowledge'],
        );

        $response->assertOk();
        $response->assertJson(['state' => DispatchState::Received->value]);
    }

    public function test_the_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('institutions.alerts.index'));
        $this->assertTrue(Route::has('institutions.alerts.dispatches.decisions.store'));
    }
}
