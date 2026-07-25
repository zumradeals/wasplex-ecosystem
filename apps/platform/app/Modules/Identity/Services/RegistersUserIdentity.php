<?php

namespace App\Modules\Identity\Services;

use App\Models\User;
use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
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
use Illuminate\Support\Str;

/**
 * Crée atomiquement le compte, la personne, la liaison active et l'état
 * d'assurance initial d'un utilisateur individuel (P003-A §7).
 *
 * Aucun compte partiellement initialisé ne peut subsister : toute étape
 * échouée annule l'ensemble de la transaction.
 *
 * Depuis P007, une inscription réelle (`LinkOrigin::Registration`) émet
 * aussi, dans la même transaction, les grants de base du rôle modèle
 * `user.base` ({@see GrantAutoIssuer}) — ferme TD-0005-D pour `wallet.view`
 * et `campaign.report` : sans cela, aucune capacité `self` déclarée par un
 * autre module ne serait jamais réellement utilisable par un compte réel.
 * Volontairement absent pour les autres origines (`system`, `migration`,
 * `support_review`) : le compte technique lui-même ({@see SystemIdentity})
 * ne doit jamais s'auto-habiliter, et un compte migré ou revu par le
 * support suit sa propre procédure d'octroi.
 */
class RegistersUserIdentity
{
    public function __construct(
        private readonly GrantAutoIssuer $grantAutoIssuer,
    ) {}

    /**
     * @param  array<string, string>  $attributes
     */
    public function register(array $attributes, LinkOrigin $origin = LinkOrigin::Registration): User
    {
        return DB::transaction(function () use ($attributes, $origin): User {
            $user = User::create($attributes);

            $person = Person::create();

            $link = PersonAccountLink::create([
                'person_id' => $person->id,
                'user_id' => $user->id,
                'status' => LinkStatus::Active,
                'origin' => $origin,
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

            if ($origin === LinkOrigin::Registration) {
                $this->grantAutoIssuer->issueRoleTemplateGrants($link, 'user.base', (string) Str::uuid());
            }

            return $user;
        });
    }
}
