<?php

namespace Tests\Feature\Modules\Alerts\Http;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Services\CaseModerationService;
use App\Modules\Alerts\Services\CaseSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class AlertCaseRouteTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_published_case_is_visible_to_anyone(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::UnderReview);
        $case = app(CaseModerationService::class)->publish($case, $reviewer, 'Sac perdu', 'Résumé', null, [], (string) Str::uuid());

        $response = $this->get('/alerts/'.$case->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('alerts/show')
            ->where('publication.title', 'Sac perdu')
            ->where('case.is_owner', false),
        );
    }

    public function test_an_unpublished_case_returns_404_for_a_stranger(): void
    {
        $case = $this->makeCommunityCase(state: CommunityCaseState::UnderReview);
        $stranger = $this->makeUser('stranger-'.Str::uuid().'@example.com');

        $response = $this->actingAs($stranger)->get('/alerts/'.$case->id);

        $response->assertNotFound();
    }

    public function test_the_owner_sees_their_own_unpublished_case_and_its_history(): void
    {
        $author = $this->makeRepresentative();
        $case = app(CaseSubmissionService::class)->proposeCommunityCase(
            $author,
            CaseCategory::LostItem,
            'Un sac perdu.',
            'CI',
            null,
            null,
            null,
            'fr',
            (string) Str::uuid(),
        );

        $response = $this->actingAs($author->user)->get('/alerts/'.$case->id);

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('alerts/show')
            ->where('case.is_owner', true)
            ->where('publication', null)
            ->has('history', 1),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('alerts.show'));
    }
}
