<?php

namespace Tests\Feature\Modules\Alerts\Http;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Services\CaseModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

/**
 * Intégration Feed (mission P008-A §15) : une alerte publiée apparaît dans
 * `community_alerts`, jamais dans `ads`, jamais comptée comme publicité.
 */
class FeedIntegrationTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_published_community_alert_appears_in_the_feed_response_never_as_an_ad(): void
    {
        $reviewer = $this->makeRepresentative();
        $case = $this->makeCommunityCase(state: CommunityCaseState::UnderReview);
        app(CaseModerationService::class)->publish($case, $reviewer, 'Sac perdu au marché', 'Résumé public.', null, [], (string) Str::uuid());

        $user = $this->makeUser('viewer-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('community_alerts', 1)
            ->where('community_alerts.0.title', 'Sac perdu au marché')
            ->where('ads', []),
        );
    }

    public function test_an_unpublished_case_never_appears_in_the_feed(): void
    {
        $this->makeCommunityCase(state: CommunityCaseState::UnderReview);

        $user = $this->makeUser('viewer-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('community_alerts', []),
        );
    }
}
