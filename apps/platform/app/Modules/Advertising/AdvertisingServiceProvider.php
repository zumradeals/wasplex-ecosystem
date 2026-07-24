<?php

namespace App\Modules\Advertising;

use Illuminate\Support\ServiceProvider;

/**
 * Frontière du module Publicité (ADR-0010) : déclare ses propres
 * migrations, sans exposer ses modèles internes en dehors de ses services
 * publics. Aucune route, aucun contrôleur, aucune capacité
 * Governance/Authorization : ce module ne construit encore aucune
 * capacité utilisable (ADR-0010 §5, §8 ; P005-A §5).
 */
class AdvertisingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
