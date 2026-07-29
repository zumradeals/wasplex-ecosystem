<?php

namespace Tests\Feature\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\DispatchState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Models\InstitutionDispatch;
use App\Modules\Alerts\Services\CaseDispatchService;
use App\Modules\Alerts\Services\Exceptions\NoEligibleInstitutionException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class CaseDispatchServiceTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_no_eligible_institution_never_produces_a_fake_transmission(): void
    {
        $case = $this->makeSosCase(category: CaseCategory::Fire, countryCode: 'CI');

        $this->expectException(NoEligibleInstitutionException::class);

        app(CaseDispatchService::class)->routeToEligibleInstitutions($case, (string) Str::uuid());
    }

    public function test_routing_selects_only_an_eligible_institution_for_the_category_and_country(): void
    {
        $institution = $this->makeInstitution('CI');
        $staff = $this->makeRepresentative();
        $this->grantInstitutionalCapability($staff, 'alert_case.receive', $institution, [CaseCategory::Fire]);

        $wrongCountryInstitution = $this->makeInstitution('SN');
        $this->grantInstitutionalCapability($this->makeRepresentative(), 'alert_case.receive', $wrongCountryInstitution, [CaseCategory::Fire]);

        $case = $this->makeSosCase(category: CaseCategory::Fire, countryCode: 'CI');

        $dispatch = app(CaseDispatchService::class)->routeToEligibleInstitutions($case, (string) Str::uuid());

        $this->assertSame($institution->id, $dispatch->organization_id);
        $this->assertSame(DispatchState::Created, $dispatch->state);
    }

    public function test_a_case_is_never_dispatched_twice_actively_to_the_same_organization(): void
    {
        $institution = $this->makeInstitution();
        $case = $this->makeSosCase();

        $service = app(CaseDispatchService::class);
        $service->createDispatch($case, $institution, (string) Str::uuid());

        $this->expectException(QueryException::class);
        $service->createDispatch($case, $institution, (string) Str::uuid());
    }

    public function test_transmission_is_not_reception_and_reception_is_not_acceptance(): void
    {
        $institution = $this->makeInstitution();
        $staff = $this->makeRepresentative();
        $case = $this->makeSosCase();

        $service = app(CaseDispatchService::class);
        $dispatch = $service->createDispatch($case, $institution, (string) Str::uuid());
        $service->transmitPending();

        $dispatch = $dispatch->fresh();
        $this->assertSame(DispatchState::Transmitted, $dispatch->state);
        $this->assertSame(SosCaseState::Transmitted->value, $case->fresh()->state, 'la transmission cascade au dossier, mais reste distincte de la réception');

        $dispatch = $service->acknowledge($dispatch, $staff, $institution, (string) Str::uuid());
        $this->assertSame(DispatchState::Received, $dispatch->state);
        $this->assertSame(SosCaseState::Received->value, $case->fresh()->state);
        $this->assertNotSame(DispatchState::Accepted, $dispatch->state);
    }

    public function test_full_sos_lifecycle_cascades_to_the_case(): void
    {
        $institution = $this->makeInstitution();
        $staff = $this->makeRepresentative();
        $case = $this->makeSosCase();

        $service = app(CaseDispatchService::class);
        $dispatch = $service->createDispatch($case, $institution, (string) Str::uuid());
        $service->transmitPending();
        $dispatch = $dispatch->fresh();

        $dispatch = $service->acknowledge($dispatch, $staff, $institution, (string) Str::uuid());
        $dispatch = $service->accept($dispatch, $staff, $institution, (string) Str::uuid());
        $dispatch = $service->process($dispatch, $staff, $institution, (string) Str::uuid());
        $dispatch = $service->resolve($dispatch, $staff, $institution, (string) Str::uuid());

        $this->assertSame(DispatchState::Resolved, $dispatch->state);
        $this->assertSame(SosCaseState::Resolved->value, $case->fresh()->state);
    }

    public function test_worker_transmission_is_idempotent_and_never_reprocesses_transmitted_dispatches(): void
    {
        $institution = $this->makeInstitution();
        $case = $this->makeSosCase();

        $service = app(CaseDispatchService::class);
        $service->createDispatch($case, $institution, (string) Str::uuid());

        $firstRun = $service->transmitPending();
        $secondRun = $service->transmitPending();

        $this->assertSame(1, $firstRun);
        $this->assertSame(0, $secondRun);
        $this->assertSame(1, InstitutionDispatch::query()->where('state', DispatchState::Transmitted)->count());
    }
}
