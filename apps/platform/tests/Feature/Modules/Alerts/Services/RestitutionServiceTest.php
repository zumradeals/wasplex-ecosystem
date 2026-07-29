<?php

namespace Tests\Feature\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\RestitutionState;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\CorrespondenceReport;
use App\Modules\Alerts\Services\CorrespondenceService;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use App\Modules\Alerts\Services\RestitutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class RestitutionServiceTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_the_code_is_never_stored_in_clear_and_a_wrong_code_is_refused(): void
    {
        [$case, $report] = $this->validatedMatch();

        $result = app(RestitutionService::class)->issueCode($case, $report, null, 30, (string) Str::uuid());
        $restitution = $result['restitution'];

        $this->assertNotSame($result['code'], $restitution->code_hash);
        $this->assertSame(RestitutionState::CodeIssued, $restitution->state);

        $deliveredBy = $this->makeRepresentative();

        $this->expectException(InvalidCaseTransitionException::class);
        app(RestitutionService::class)->confirmDelivery($restitution, $deliveredBy, 'code-invalide', (string) Str::uuid());
    }

    public function test_delivery_then_reception_are_two_distinct_confirmations(): void
    {
        [$case, $report] = $this->validatedMatch();

        $result = app(RestitutionService::class)->issueCode($case, $report, null, 30, (string) Str::uuid());
        $restitution = $result['restitution'];
        $code = $result['code'];

        $deliveredBy = $this->makeRepresentative();
        $service = app(RestitutionService::class);

        $restitution = $service->confirmDelivery($restitution, $deliveredBy, $code, (string) Str::uuid());
        $this->assertSame(RestitutionState::Delivered, $restitution->state);
        $this->assertNull($restitution->received_at);

        $receivedBy = $this->makeRepresentative();
        $restitution = $service->confirmReception($restitution, $receivedBy, null, (string) Str::uuid());
        $this->assertSame(RestitutionState::Received, $restitution->state);
        $this->assertNotSame($restitution->delivered_confirmed_by_person_account_link_id, $restitution->received_confirmed_by_person_account_link_id);
    }

    public function test_completing_a_restitution_resolves_the_case(): void
    {
        [$case, $report] = $this->validatedMatch();

        $service = app(RestitutionService::class);
        $result = $service->issueCode($case, $report, null, 30, (string) Str::uuid());
        $restitution = $result['restitution'];

        $deliveredBy = $this->makeRepresentative();
        $restitution = $service->confirmDelivery($restitution, $deliveredBy, $result['code'], (string) Str::uuid());
        $restitution = $service->confirmReception($restitution, $this->makeRepresentative(), null, (string) Str::uuid());
        $restitution = $service->complete($restitution, $deliveredBy, (string) Str::uuid());

        $this->assertSame(RestitutionState::Completed, $restitution->state);
        $this->assertSame(CommunityCaseState::Resolved->value, $case->fresh()->state);
        $this->assertNotNull($case->fresh()->closed_at);
    }

    /**
     * @return array{0: AlertCase, 1: CorrespondenceReport}
     */
    private function validatedMatch(): array
    {
        $case = $this->makeCommunityCase(state: CommunityCaseState::Published);
        $report = app(CorrespondenceService::class)->report($case, $this->makeRepresentative(), 'x', ['a' => 'b'], (string) Str::uuid());
        app(CorrespondenceService::class)->validate($report, $this->makeRepresentative(), (string) Str::uuid());

        return [$case->fresh(), $report->fresh()];
    }
}
