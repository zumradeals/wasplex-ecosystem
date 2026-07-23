<?php

namespace App\Modules\Governance\Authorization\Integration\Http;

use App\Modules\Governance\Authorization\Integration\AuthenticatedSubject;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubjectResolver;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\SessionAssuranceResolver;
use Illuminate\Http\Request;

/**
 * Résout le sujet authentifié d'une requête HTTP à partir du compte
 * réellement authentifié par Laravel et de la force de session réellement
 * prouvée par cette requête (P003-B2 §D).
 *
 * Composant injectable réutilisable par un futur contrôleur : il ne
 * protège aucune route par lui-même et ne fait qu'assembler
 * {@see SessionAssuranceResolver} et {@see AuthenticatedSubjectResolver}.
 */
final class AuthenticatedSubjectHttpResolver
{
    public function __construct(
        private readonly SessionAssuranceResolver $sessionAssuranceResolver,
        private readonly AuthenticatedSubjectResolver $subjectResolver,
    ) {}

    /**
     * @throws SubjectResolutionFailedException
     */
    public function resolve(Request $request, ?string $claimedMembershipId = null): AuthenticatedSubject
    {
        $sessionAssurance = $this->sessionAssuranceResolver->fromRequest($request);

        return $this->subjectResolver->resolve($request->user(), $sessionAssurance, $claimedMembershipId);
    }
}
