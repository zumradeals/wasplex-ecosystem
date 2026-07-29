<?php

namespace App\Modules\Alerts;

use App\Modules\Alerts\Console\Commands\TransmitPendingDispatches;
use App\Modules\Alerts\Contracts\Health\EmergencyHealthSnapshotProvider;
use App\Modules\Alerts\Contracts\Health\NullEmergencyHealthSnapshotProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Frontière du module Alerts (P008-A) : déclare ses propres migrations, sa
 * commande d'outbox, et son contrat frontière avec Santé (article 23,
 * AMD-0016) — jamais construit vers une implémentation réelle avant
 * P009-B.
 */
class AlertsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EmergencyHealthSnapshotProvider::class, NullEmergencyHealthSnapshotProvider::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TransmitPendingDispatches::class,
            ]);
        }
    }
}
