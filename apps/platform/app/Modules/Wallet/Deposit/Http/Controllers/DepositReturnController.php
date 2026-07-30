<?php

namespace App\Modules\Wallet\Deposit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Deposit\Models\Deposit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page de retour après redirection GeniusPay (`success_url`/`error_url`
 * — la même URL sert les deux, ADR-0007 §4 : un retour de redirection ne
 * prouve jamais un paiement confirmé). L'état affiché vient toujours d'une
 * lecture fraîche du dépôt en base, jamais d'un paramètre de requête —
 * webhook, initiation ou reprise ont pu déjà le faire progresser
 * différemment de ce que suggère l'URL de retour du navigateur.
 */
class DepositReturnController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function show(Request $request, Deposit $deposit): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render('wallet/deposit-return', [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'deposit' => null,
            ]);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'wallet.view',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'wallet.deposit',
                resourceId: $deposit->id,
                organizationId: null,
                ownerPersonId: $deposit->person_id,
                countryCode: $deposit->country_code,
                territoryCodes: [],
                environment: $this->currentEnvironment(),
            ),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return Inertia::render('wallet/deposit-return', [
                'access' => ['allowed' => false, 'reason' => $exception->result->reason->code],
                'deposit' => null,
            ]);
        }

        return Inertia::render('wallet/deposit-return', [
            'access' => ['allowed' => true, 'reason' => null],
            'deposit' => [
                'id' => $deposit->id,
                'state' => $deposit->state->value,
                'amount' => $deposit->amount,
                'currency' => $deposit->currency,
            ],
        ]);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
