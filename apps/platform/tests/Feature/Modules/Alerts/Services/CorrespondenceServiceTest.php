<?php

namespace Tests\Feature\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\CorrespondenceReviewState;
use App\Modules\Alerts\Services\CorrespondenceService;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class CorrespondenceServiceTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_reporting_a_correspondence_never_decides_the_case_by_itself(): void
    {
        $reporter = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::Published);

        $report = app(CorrespondenceService::class)->report(
            $case,
            $reporter,
            'Je pense reconnaître ce sac.',
            ['couleur' => 'bleu'],
            (string) Str::uuid(),
        );

        $this->assertSame(CorrespondenceReviewState::Pending, $report->review_state);
        $this->assertSame(CommunityCaseState::Published->value, $case->fresh()->state, 'une correspondance proposée seule ne décide jamais du dossier (AMD-0007 §9)');
    }

    public function test_validating_a_correspondence_moves_the_case_to_matched(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::Published);
        $report = app(CorrespondenceService::class)->report($case, $this->makeRepresentative(), 'x', ['a' => 'b'], (string) Str::uuid());

        $report = app(CorrespondenceService::class)->validate($report, $reviewer, (string) Str::uuid());

        $this->assertSame(CorrespondenceReviewState::Validated, $report->review_state);
        $this->assertSame(CommunityCaseState::Matched->value, $case->fresh()->state);
    }

    public function test_rejecting_a_correspondence_does_not_affect_the_case(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::Published);
        $report = app(CorrespondenceService::class)->report($case, $this->makeRepresentative(), 'x', ['a' => 'b'], (string) Str::uuid());

        $report = app(CorrespondenceService::class)->reject($report, $reviewer, (string) Str::uuid());

        $this->assertSame(CorrespondenceReviewState::Rejected, $report->review_state);
        $this->assertSame(CommunityCaseState::Published->value, $case->fresh()->state);
    }

    public function test_validating_a_correspondence_on_a_case_not_ready_for_matching_is_refused(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::Draft);
        $report = app(CorrespondenceService::class)->report($case, $this->makeRepresentative(), 'x', ['a' => 'b'], (string) Str::uuid());

        $this->expectException(InvalidCaseTransitionException::class);

        app(CorrespondenceService::class)->validate($report, $reviewer, (string) Str::uuid());
    }
}
