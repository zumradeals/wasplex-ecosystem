<?php

namespace App\Modules\Alerts\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\CorrespondenceReviewState;
use App\Modules\Alerts\Enums\RestitutionState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\CorrespondenceReport;
use App\Modules\Alerts\Models\Restitution;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Destination admin « Alertes et institutions » (UX-0001 §8 ; mission
 * P008-A §17) : revue des dossiers communautaires, supervision des SOS non
 * routés, validation de correspondances, suivi des contestations de
 * restitution. Chaque section reste gouvernée par sa propre capacité,
 * aucun rôle « admin » générique (même discipline que
 * `ModerationOverviewController`).
 */
class AdminAlertsController extends Controller
{
    use ResolvesStaffVisibility;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'caseReview' => ['access' => ['allowed' => false, 'reason' => $reason], 'items' => []],
            'sosSupervision' => ['access' => ['allowed' => false, 'reason' => $reason], 'items' => []],
            'correspondenceValidation' => ['access' => ['allowed' => false, 'reason' => $reason], 'items' => []],
            'restitutionDisputes' => ['access' => ['allowed' => false, 'reason' => $reason], 'items' => []],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/alerts', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $link = $resolved['link'];

        $canReview = $this->hasActiveStaffGrant($link, 'alert_case.review');
        $canPublish = $this->hasActiveStaffGrant($link, 'alert_case.publish');
        $canSuperviseSos = $this->hasActiveStaffGrant($link, 'alert_case.review');
        $canValidateMatch = $this->hasActiveStaffGrant($link, 'alert_match.validate');
        $canVerifyReturn = $this->hasActiveStaffGrant($link, 'alert_return.verify');

        return Inertia::render('admin/alerts', [
            'caseReview' => [
                'access' => $this->accessFor($canReview || $canPublish),
                'items' => ($canReview || $canPublish) ? $this->pendingCommunityCases() : [],
            ],
            'sosSupervision' => [
                'access' => $this->accessFor($canSuperviseSos),
                'items' => $canSuperviseSos ? $this->unroutedSosCases() : [],
            ],
            'correspondenceValidation' => [
                'access' => $this->accessFor($canValidateMatch),
                'items' => $canValidateMatch ? $this->pendingCorrespondenceReports() : [],
            ],
            'restitutionDisputes' => [
                'access' => $this->accessFor($canVerifyReturn),
                'items' => $canVerifyReturn ? $this->disputedRestitutions() : [],
            ],
        ]);
    }

    /**
     * @return array{allowed: bool, reason: string|null}
     */
    private function accessFor(bool $allowed): array
    {
        return ['allowed' => $allowed, 'reason' => $allowed ? null : 'no_active_grant'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingCommunityCases(): array
    {
        return AlertCase::query()
            ->where('nature', 'community')
            ->whereIn('state', [CommunityCaseState::Submitted->value, CommunityCaseState::UnderReview->value])
            ->orderBy('created_at')
            ->get()
            ->map(fn (AlertCase $case): array => [
                'case_id' => $case->id,
                'category' => $case->category->value,
                'state' => $case->state,
                'requires_reinforced_review' => $case->category->requiresReinforcedReview(),
                'source_description' => $case->source_description,
                'created_at' => $case->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unroutedSosCases(): array
    {
        return AlertCase::query()
            ->where('nature', 'sos')
            ->whereIn('state', [
                SosCaseState::Created->value,
                SosCaseState::Unanswered->value,
                SosCaseState::Impossible->value,
            ])
            ->orderBy('created_at')
            ->get()
            ->map(fn (AlertCase $case): array => [
                'case_id' => $case->id,
                'category' => $case->category->value,
                'state' => $case->state,
                'source_description' => $case->source_description,
                'recall_phone' => $case->recall_phone,
                'created_at' => $case->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingCorrespondenceReports(): array
    {
        return CorrespondenceReport::query()
            ->whereIn('review_state', [CorrespondenceReviewState::Pending->value, CorrespondenceReviewState::Candidate->value])
            ->with('case')
            ->orderBy('created_at')
            ->get()
            ->map(fn (CorrespondenceReport $report): array => [
                'correspondence_report_id' => $report->id,
                'case_id' => $report->case_id,
                'case_category' => $report->case->category->value,
                'non_public_description' => $report->non_public_description,
                'created_at' => $report->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function disputedRestitutions(): array
    {
        return Restitution::query()
            ->where('state', RestitutionState::Disputed->value)
            ->with('case')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Restitution $restitution): array => [
                'restitution_id' => $restitution->id,
                'case_id' => $restitution->case_id,
                'case_category' => $restitution->case->category->value,
                'dispute_reason' => $restitution->dispute_reason,
                'created_at' => $restitution->created_at->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
