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
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Première route sensible réelle du module Wallet (P006-A) : consultation,
 * par une personne authentifiée, de son propre solde WP — jamais celui
 * d'autrui (portée `self`, voir `wallet.view`, migration `2026_07_25_200001`).
 *
 * Ne construit aucun cycle de retrait, historique ni état provisoire/réservé
 * (ecosystem/wallet/02, hors périmètre de ce lot — voir TD-0005).
 */
class WalletBalanceController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly PersonBalanceProjection $balanceProjection,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
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
            return $this->failureResponder->forOutcome($exception);
        }

        return response()->json([
            'balances' => $this->balanceProjection->forPerson($personId),
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
