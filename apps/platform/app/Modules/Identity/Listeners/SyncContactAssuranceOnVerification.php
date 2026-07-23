<?php

namespace App\Modules\Identity\Listeners;

use App\Models\User;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Models\AssuranceState;
use Illuminate\Auth\Events\Verified;

/**
 * Synchronise contact_assurance=confirmed après un événement de vérification
 * d'e-mail réellement survenu (Illuminate\Auth\Events\Verified).
 *
 * Ce point d'intégration Laravel est standard, testé par le socle Fortify
 * existant, et ne simule jamais une preuve : il ne réagit qu'à un événement
 * effectivement déclenché par la vérification (P003-A §7).
 */
class SyncContactAssuranceOnVerification
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        AssuranceState::query()
            ->where('user_id', $event->user->getKey())
            ->update(['contact_assurance' => ContactAssurance::Confirmed]);
    }
}
