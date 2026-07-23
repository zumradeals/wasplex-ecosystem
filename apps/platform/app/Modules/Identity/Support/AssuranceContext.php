<?php

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;

/**
 * Représentation immuable des axes d'assurance d'un compte à un instant donné,
 * y compris la force de la session courante.
 *
 * Ce type n'est pas persisté : il ne fabrique aucun score global et ne
 * confond jamais la force de session avec un niveau KYC permanent (cf. P003-A §6).
 */
final readonly class AssuranceContext
{
    public function __construct(
        public AccountState $accountState,
        public ContactAssurance $contactAssurance,
        public IdentityAssurance $identityAssurance,
        public UniquenessAssurance $uniquenessAssurance,
        public OrganizationStatus $organizationStatus,
        public SessionAssurance $sessionAssurance,
    ) {}
}
