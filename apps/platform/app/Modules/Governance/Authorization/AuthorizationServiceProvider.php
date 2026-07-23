<?php

namespace App\Modules\Governance\Authorization;

use Illuminate\Support\ServiceProvider;

/**
 * Frontière du module Governance/Authorization (ADR-0001, ADR-0006) :
 * déclare ses propres migrations, sans exposer ses modèles internes en
 * dehors de ses contrats et services publics.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
