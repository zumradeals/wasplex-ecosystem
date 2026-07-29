<?php

namespace Tests\Feature\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Services\CaseSubmissionService;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class CaseSubmissionServiceTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_community_case_is_created_transactionally_with_its_first_event(): void
    {
        $author = $this->makeRepresentative();

        $case = app(CaseSubmissionService::class)->proposeCommunityCase(
            author: $author,
            category: CaseCategory::LostItem,
            sourceDescription: 'Un sac perdu.',
            countryCode: 'CI',
            territoryCode: null,
            exactLocation: null,
            recallPhone: null,
            locale: 'fr',
            correlationId: (string) Str::uuid(),
        );

        $this->assertSame(CommunityCaseState::Draft->value, $case->state);
        $this->assertSame(1, $case->events()->count());
        $this->assertSame('case_created', $case->events()->first()->event_type);
    }

    public function test_submitting_a_draft_transitions_to_submitted_with_an_event(): void
    {
        $author = $this->makeRepresentative();
        $case = $this->makeCommunityCase($author, state: CommunityCaseState::Draft);

        $service = app(CaseSubmissionService::class);
        $case = $service->submitCommunityCase($case, $author, (string) Str::uuid());

        $this->assertSame(CommunityCaseState::Submitted->value, $case->state);
        $this->assertSame('case_submitted', $case->events()->latest('occurred_at')->first()->event_type);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $author = $this->makeRepresentative();
        $case = $this->makeCommunityCase($author, state: CommunityCaseState::Published);

        $this->expectException(InvalidCaseTransitionException::class);

        app(CaseSubmissionService::class)->submitCommunityCase($case, $author, (string) Str::uuid());
    }

    public function test_a_sos_case_can_be_created_without_an_author(): void
    {
        $case = app(CaseSubmissionService::class)->reportSos(
            author: null,
            category: CaseCategory::Fire,
            sourceDescription: 'Un incendie.',
            countryCode: 'CI',
            territoryCode: null,
            exactLocation: null,
            recallPhone: null,
            locale: 'fr',
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        $this->assertNull($case->author_person_account_link_id);
        $this->assertSame(SosCaseState::Created->value, $case->state);
    }

    public function test_sos_submission_is_idempotent(): void
    {
        $idempotencyKey = (string) Str::uuid();
        $service = app(CaseSubmissionService::class);

        $first = $service->reportSos(
            author: null,
            category: CaseCategory::Accident,
            sourceDescription: 'Un accident.',
            countryCode: 'CI',
            territoryCode: null,
            exactLocation: null,
            recallPhone: null,
            locale: 'fr',
            idempotencyKey: $idempotencyKey,
            correlationId: (string) Str::uuid(),
        );

        $second = $service->reportSos(
            author: null,
            category: CaseCategory::Accident,
            sourceDescription: 'Un accident.',
            countryCode: 'CI',
            territoryCode: null,
            exactLocation: null,
            recallPhone: null,
            locale: 'fr',
            idempotencyKey: $idempotencyKey,
            correlationId: (string) Str::uuid(),
        );

        $this->assertSame($first->id, $second->id);
    }

    public function test_a_community_category_cannot_be_used_to_report_a_sos(): void
    {
        $this->expectException(InvalidCaseTransitionException::class);

        app(CaseSubmissionService::class)->reportSos(
            author: null,
            category: CaseCategory::LostItem,
            sourceDescription: 'Invalide.',
            countryCode: 'CI',
            territoryCode: null,
            exactLocation: null,
            recallPhone: null,
            locale: 'fr',
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
    }
}
