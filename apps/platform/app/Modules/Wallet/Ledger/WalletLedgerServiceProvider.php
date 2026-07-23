<?php

namespace App\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use Illuminate\Support\ServiceProvider;

/**
 * Frontière du module Wallet/Ledger (ADR-0003, architecture/12) : déclare
 * ses propres migrations, sans exposer ses modèles internes en dehors de
 * {@see LedgerPoster} et de ses
 * projections publiques. Aucune route, aucun contrôleur : ce module ne
 * construit encore aucune capacité utilisable (P004-A §3.E).
 */
class WalletLedgerServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
