<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_ok_when_database_is_available(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $response->assertExactJson([
            'status' => 'ok',
            'application' => 'wasplex',
            'database' => 'ok',
        ]);
    }

    public function test_health_endpoint_hides_internal_details_when_database_is_unavailable(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->andThrow(new \RuntimeException('SQLSTATE[08006] password authentication failed for user "wasplex_app"'));

        $response = $this->getJson('/health');

        $response->assertStatus(503);
        $response->assertJson(['status' => 'unavailable']);

        $response->assertDontSee('SQLSTATE', false);
        $response->assertDontSee('wasplex_app', false);
        $response->assertDontSee('password', false);
    }
}
