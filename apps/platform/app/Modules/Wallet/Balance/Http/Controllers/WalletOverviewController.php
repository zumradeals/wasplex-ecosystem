<?php

namespace App\Modules\Wallet\Balance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Premier écran de production réel du Wallet (P006-A2, `U-006-01-apercu-wallet`,
 * UX-0002 §3.3) : reprend la même autorisation que la route JSON
 * {@see WalletBalanceController} (`wallet.view`, portée `self` — jamais le
 * solde d'autrui), mais restitue un état d'écran plutôt qu'une erreur JSON
 * brute en cas de refus, conformément à CLAUDE.md §6 (« tout écran expose
 * les états chargement, vide, erreur, hors ligne et inconnu pertinents »).
 *
 * Cette route vit dans le groupe `auth`/`verified` (comme `dashboard`) :
 * un visiteur non authentifié est redirigé vers la connexion par le
 * middleware lui-même, jamais par ce contrôleur. Seul le refus
 * d'autorisation (grant `wallet.view` absent, TD-0005-D : aucun octroi
 * automatique n'existe encore) reste à afficher dans la page elle-même —
 * jamais un JSON brut sur un chargement de page complète.
 */
class WalletOverviewController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly PersonBalanceProjection $balanceProjection,
    ) {}

    public function show(Request $request): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            // Ne devrait pas survenir derrière le middleware 'auth' (tout
            // compte authentifié possède déjà sa liaison personne, créée à
            // l'inscription par RegistersUserIdentity) ; traité comme un
            // refus d'écran plutôt qu'une erreur non gérée, par prudence.
            return Inertia::render('wallet/overview', [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'balances' => [],
            ]);
        }

        $personId = $subject->personAccountLink->person_id;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'wallet.view',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'wallet.balance',
                resourceId: $personId,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: null,
                territoryCodes: [],
                environment: $this->currentEnvironment(),
            ),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return Inertia::render('wallet/overview', [
                'access' => ['allowed' => false, 'reason' => $exception->result->reason->code],
                'balances' => [],
            ]);
        }

        return Inertia::render('wallet/overview', [
            'access' => ['allowed' => true, 'reason' => null],
            'balances' => $this->balanceProjection->forPerson($personId),
        ]);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
