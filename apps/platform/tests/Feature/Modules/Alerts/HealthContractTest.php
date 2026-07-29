<?php

namespace Tests\Feature\Modules\Alerts;

use App\Modules\Alerts\Contracts\Health\EmergencyHealthSnapshotProvider;
use App\Modules\Alerts\Contracts\Health\EmergencyHealthSnapshotUnavailable;
use App\Modules\Alerts\Contracts\Health\NullEmergencyHealthSnapshotProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * Frontière Santé (article 23, AMD-0016 ; ecosystem/sante/00). Alertes
 * fonctionne intégralement sans Santé dans ce lot (P008-A) — le contrat
 * est anticipé, jamais implémenté vers une vraie donnée médicale.
 */
class HealthContractTest extends AlertsTestCase
{
    use RefreshDatabase;

    public function test_the_default_provider_is_bound_and_always_returns_unavailable(): void
    {
        $provider = app(EmergencyHealthSnapshotProvider::class);

        $this->assertInstanceOf(NullEmergencyHealthSnapshotProvider::class, $provider);

        $case = $this->makeCommunityCase();
        $result = $provider->forCase($case);

        $this->assertInstanceOf(EmergencyHealthSnapshotUnavailable::class, $result);
        $this->assertSame('health_domain_not_available', $result->reason);
    }

    public function test_no_health_schema_or_table_exists_in_this_lot(): void
    {
        $this->assertFalse(
            Schema::hasTable('health.patients'),
            'aucune table health.* ne doit exister avant P009-B',
        );
    }
}
