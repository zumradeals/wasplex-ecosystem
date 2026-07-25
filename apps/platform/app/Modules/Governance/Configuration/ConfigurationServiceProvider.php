<?php

namespace App\Modules\Governance\Configuration;

use Illuminate\Support\ServiceProvider;

/**
 * Frontière du module Governance/Configuration (W2, ADR-0002) : déclare ses
 * propres migrations, sans exposer ses modèles internes en dehors de ses
 * services publics (même discipline que `AuthorizationServiceProvider`).
 */
class ConfigurationServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
