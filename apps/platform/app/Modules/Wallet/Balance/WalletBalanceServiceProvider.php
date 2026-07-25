<?php

namespace App\Modules\Wallet\Balance;

use Illuminate\Support\ServiceProvider;

/**
 * Frontière du sous-module Wallet/Balance (P006-A) : le seul endroit du
 * Wallet à exposer une route et une capacité réelles. `Wallet\Ledger`
 * (P004-A) reste délibérément dépourvu de tout HTTP — voir son
 * ServiceProvider.
 */
class WalletBalanceServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
