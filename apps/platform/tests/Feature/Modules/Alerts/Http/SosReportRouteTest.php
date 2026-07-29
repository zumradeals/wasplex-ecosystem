<?php

namespace Tests\Feature\Modules\Alerts\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Modules\Alerts\AlertsTestCase;

class SosReportRouteTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_report_a_sos_without_authentication(): void
    {
        $response = $this->postJson('/alerts/sos', [
            'category' => 'fire',
            'source_description' => 'Un incendie dans un immeuble.',
            'country_code' => 'CI',
            'locale' => 'fr',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertCreated();
        $response->assertJson(['state' => 'created', 'routed' => false]);
    }

    public function test_a_community_category_is_refused_on_the_sos_route(): void
    {
        $response = $this->postJson('/alerts/sos', [
            'category' => 'lost_item',
            'source_description' => 'x',
            'country_code' => 'CI',
            'locale' => 'fr',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(422);
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('alerts.sos.store'));
    }
}
