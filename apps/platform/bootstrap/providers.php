<?php

use App\Modules\Advertising\AdvertisingServiceProvider;
use App\Modules\Alerts\AlertsServiceProvider;
use App\Modules\Governance\Authorization\AuthorizationServiceProvider;
use App\Modules\Governance\Configuration\ConfigurationServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Wallet\Balance\WalletBalanceServiceProvider;
use App\Modules\Wallet\Deposit\WalletDepositServiceProvider;
use App\Modules\Wallet\Ledger\WalletLedgerServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IdentityServiceProvider::class,
    AuthorizationServiceProvider::class,
    ConfigurationServiceProvider::class,
    WalletLedgerServiceProvider::class,
    WalletBalanceServiceProvider::class,
    WalletDepositServiceProvider::class,
    AdvertisingServiceProvider::class,
    AlertsServiceProvider::class,
];
