<?php

namespace Tests\Feature\Modules\Alerts\Http;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\CorrespondenceReviewState;
use App\Modules\Alerts\Services\CorrespondenceService;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class AdminAlertsRouteTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/alerts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/alerts')
            ->where('caseReview.access.allowed', false)
            ->where('caseReview.items', [])
            ->where('sosSupervision.access.allowed', false)
            ->where('correspondenceValidation.access.allowed', false)
            ->where('restitutionDisputes.access.allowed', false),
        );
    }

    public function test_a_holder_of_alert_case_review_sees_pending_community_cases(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'alert_case.review', 'alerts.case_category');

        $this->makeCommunityCase(state: CommunityCaseState::Submitted);

        $response = $this->actingAs($staff->user)->get('/admin/alerts');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/alerts')
            ->where('caseReview.access.allowed', true)
            ->has('caseReview.items', 1),
        );
    }

    public function test_review_then_publish_moves_the_case_through_the_admin_route(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'alert_case.review', 'alerts.case_category');
        $this->grantStaffCapability($staff, 'alert_case.publish', 'alerts.case_category');

        $case = $this->makeCommunityCase(state: CommunityCaseState::Submitted);

        $review = $this->actingAs($staff->user)->postJson("/admin/alerts/cases/{$case->id}/decisions", [
            'decision' => 'start_review',
        ]);
        $review->assertOk();
        $review->assertJson(['state' => CommunityCaseState::UnderReview->value]);

        $publish = $this->actingAs($staff->user)->postJson("/admin/alerts/cases/{$case->id}/decisions", [
            'decision' => 'publish',
            'title' => 'Sac perdu',
            'summary' => 'Résumé public.',
        ]);
        $publish->assertOk();
        $publish->assertJson(['state' => CommunityCaseState::Published->value]);
    }

    public function test_validating_a_correspondence_through_the_admin_route_requires_the_capability(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'alert_match.validate', 'alerts.case_category');

        $case = $this->makeCommunityCase(state: CommunityCaseState::Published);
        $report = app(CorrespondenceService::class)->report($case, $this->makeRepresentative(), 'x', ['a' => 'b'], (string) Str::uuid());

        $response = $this->actingAs($staff->user)->postJson(
            "/admin/alerts/correspondence-reports/{$report->id}/decisions",
            ['decision' => 'validate'],
        );

        $response->assertOk();
        $response->assertJson(['review_state' => CorrespondenceReviewState::Validated->value]);
    }

    public function test_the_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('admin.alerts'));
        $this->assertTrue(Route::has('admin.alerts.cases.decisions.store'));
        $this->assertTrue(Route::has('admin.alerts.correspondence-reports.decisions.store'));
    }
}
