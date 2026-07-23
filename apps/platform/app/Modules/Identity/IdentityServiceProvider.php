<?php

namespace App\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Listeners\SyncContactAssuranceOnVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Frontière du module Identité (ADR-0001, ADR-0006) : déclare ses propres
 * migrations et ses points d'intégration, sans exposer ses modèles internes
 * en dehors de ses contrats.
 */
class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(Verified::class, SyncContactAssuranceOnVerification::class);

        // L'identifiant public (P003-A §6) appartient au domaine Identité :
        // il est renseigné ici plutôt que dans App\Models\User, qui reste
        // le simple adaptateur d'authentification Fortify pendant P003-A.
        User::creating(function (User $user): void {
            $user->public_id ??= (string) Str::uuid7();
        });
    }
}
