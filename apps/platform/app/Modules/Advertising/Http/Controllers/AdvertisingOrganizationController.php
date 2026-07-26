<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Organisation et accès (UX-0001 §8) : dossier annonceur du représentant
 * courant. Déclaration inchangée ({@see AdvertiserProfileController}) —
 * cet écran n'ajoute aucun champ ni aucune capacité, il donne seulement au
 * formulaire de déclaration existant sa propre destination dans la
 * navigation professionnelle plutôt que de rester imbriqué dans la Vue
 * d'ensemble.
 */
class AdvertisingOrganizationController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/organization');

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        return Inertia::render('advertising/organization', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
        ]);
    }
}
