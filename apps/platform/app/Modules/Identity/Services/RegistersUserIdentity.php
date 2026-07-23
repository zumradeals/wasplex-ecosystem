<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;

/**
 * Crée atomiquement le compte, la personne, la liaison active et l'état
 * d'assurance initial d'un utilisateur individuel (P003-A §7).
 *
 * Aucun compte partiellement initialisé ne peut subsister : toute étape
 * échouée annule l'ensemble de la transaction.
 */
class RegistersUserIdentity
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function register(array $attributes): User
    {
        return DB::transaction(function () use ($attributes): User {
            $user = User::create($attributes);

            $person = Person::create();

            PersonAccountLink::create([
                'person_id' => $person->id,
                'user_id' => $user->id,
                'status' => LinkStatus::Active,
                'origin' => LinkOrigin::Registration,
            ]);

            AssuranceState::create([
                'user_id' => $user->id,
                'account_state' => AccountState::Active,
                'contact_assurance' => $user->email_verified_at !== null
                    ? ContactAssurance::Confirmed
                    : ContactAssurance::Unconfirmed,
                'identity_assurance' => IdentityAssurance::Undeclared,
                'uniqueness_assurance' => UniquenessAssurance::Unknown,
                'organization_status' => OrganizationStatus::None,
            ]);

            return $user;
        });
    }
}
