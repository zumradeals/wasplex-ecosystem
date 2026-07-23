<?php

namespace App\Modules\Governance\Authorization\Integration;

use App\Models\User;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Support\AssuranceContext;

/**
 * Sujet authentifié, entièrement résolu et vérifié côté serveur (P003-B2 §A).
 *
 * Une instance n'existe que si le compte, sa liaison personne-compte et,
 * lorsqu'une appartenance est revendiquée, son appartenance active et
 * réellement liée à ce compte, ont tous été confirmés en base — jamais
 * simplement acceptés depuis une donnée cliente.
 */
final readonly class AuthenticatedSubject
{
    public function __construct(
        public User $account,
        public PersonAccountLink $personAccountLink,
        public AssuranceContext $assurance,
        public ?Membership $membership,
    ) {}
}
