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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisionnement paresseux de l'unique compte technique interne de Wasplex
 * (P007, ADR-0004 §3.3). N'existe que pour servir d'auteur de référence aux
 * octrois automatiques ({@see GrantAutoIssuer}) —
 * jamais pour une connexion interactive : le mot de passe généré n'est
 * communiqué à personne et aucune route ne l'expose.
 *
 * Un seul compte existe jamais, identifié par une adresse fixe non
 * atteignable (domaine `.internal`, jamais un domaine réel de messagerie).
 *
 * Reproduit délibérément, plutôt que réutiliser
 * {@see RegistersUserIdentity::register()}, les quelques insertions
 * nécessaires à la création d'un compte : `RegistersUserIdentity` dépend
 * de `GrantAutoIssuer`, qui dépend lui-même de cette classe pour trouver
 * son auteur — une dépendance circulaire entre les deux services de
 * création de compte. Le compte technique n'a de toute façon jamais
 * vocation à recevoir de grants automatiques pour lui-même (voir la garde
 * `origin === LinkOrigin::Registration` de `RegistersUserIdentity`).
 */
class SystemIdentity
{
    private const EMAIL = 'system-provisioning@wasplex.internal';

    public function provisioningAccount(): PersonAccountLink
    {
        $existing = $this->findActiveLink();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function (): PersonAccountLink {
                $user = User::create([
                    'name' => 'Wasplex — provisionnement automatique',
                    'email' => self::EMAIL,
                    'password' => Str::random(64),
                ]);

                $person = Person::create();

                $link = PersonAccountLink::create([
                    'person_id' => $person->id,
                    'user_id' => $user->id,
                    'status' => LinkStatus::Active,
                    'origin' => LinkOrigin::System,
                ]);

                AssuranceState::create([
                    'user_id' => $user->id,
                    'account_state' => AccountState::Active,
                    'contact_assurance' => ContactAssurance::Unconfirmed,
                    'identity_assurance' => IdentityAssurance::Undeclared,
                    'uniqueness_assurance' => UniquenessAssurance::Unknown,
                    'organization_status' => OrganizationStatus::None,
                ]);

                return $link;
            });
        } catch (QueryException $exception) {
            // Provisionnement concurrent du même compte technique unique
            // (rare, premier appel de tout l'écosystème) : le gagnant de la
            // course sur la contrainte unique de l'e-mail fait autorité
            // (même garde que SharedLedgerAccounts::getOrCreate()).
            $existing = $this->findActiveLink();

            if ($existing !== null) {
                return $existing;
            }

            throw $exception;
        }
    }

    private function findActiveLink(): ?PersonAccountLink
    {
        return PersonAccountLink::query()
            ->whereHas('user', fn ($query) => $query->where('email', self::EMAIL))
            ->where('status', 'active')
            ->first();
    }
}
