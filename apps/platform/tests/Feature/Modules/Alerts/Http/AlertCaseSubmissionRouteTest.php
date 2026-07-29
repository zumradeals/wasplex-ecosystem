<?php

namespace Tests\Feature\Modules\Alerts\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class AlertCaseSubmissionRouteTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_guest_receives_a_structured_401_not_a_redirect(): void
    {
        $response = $this->postJson('/alerts/community', [
            'category' => 'lost_item',
            'source_description' => 'Un sac perdu.',
            'country_code' => 'CI',
            'locale' => 'fr',
        ]);

        $response->assertStatus(401);
        $response->assertJsonStructure(['decision']);
    }

    public function test_an_authenticated_user_can_submit_a_community_case(): void
    {
        $user = $this->makeUser('declarant-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson('/alerts/community', [
            'category' => 'lost_item',
            'source_description' => 'Un sac perdu près du marché.',
            'country_code' => 'CI',
            'locale' => 'fr',
        ]);

        $response->assertCreated();
        $response->assertJson(['state' => 'submitted']);
    }

    public function test_a_sos_category_is_refused_on_the_community_route(): void
    {
        $user = $this->makeUser('declarant-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson('/alerts/community', [
            'category' => 'fire',
            'source_description' => 'x',
            'country_code' => 'CI',
            'locale' => 'fr',
        ]);

        $response->assertStatus(422);
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('alerts.community.store'));
    }
}
