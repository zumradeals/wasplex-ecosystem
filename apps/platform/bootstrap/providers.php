<?php

use App\Modules\Advertising\AdvertisingServiceProvider;
use App\Modules\Governance\Authorization\AuthorizationServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Wallet\Ledger\WalletLedgerServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    IdentityServiceProvider::class,
    AuthorizationServiceProvider::class,
    WalletLedgerServiceProvider::class,
    AdvertisingServiceProvider::class,
];
