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
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Deposit\Http\Requests\StoreDepositRequest;
use App\Modules\Wallet\Deposit\Services\DepositInitiationService;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Première route sensible réelle du dépôt Wallet (AMD-0017 ;
 * ecosystem/wallet/05). Initie un dépôt (`wallet.deposit`, portée self —
 * une personne ne recharge jamais le wallet d'autrui) et retourne l'URL de
 * checkout GeniusPay : ce contrôleur ne crédite jamais lui-même de valeur,
 * seul le webhook signé le fait
 * ({@see DepositWebhookController}).
 */
class DepositInitiationController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly DepositInitiationService $depositInitiationService,
    ) {}

    public function store(StoreDepositRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $personId = $subject->personAccountLink->person_id;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'wallet.deposit',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'wallet.deposit',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: 'CI',
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

        $depositId = (string) Str::uuid7();

        try {
            $deposit = $this->depositInitiationService->initiate(
                depositId: $depositId,
                personId: $personId,
                initiatedByPersonAccountLinkId: $subject->personAccountLink->id,
                amount: (int) $request->validated('amount'),
                successUrl: URL::route('wallet.deposits.return', ['deposit' => $depositId]),
                errorUrl: URL::route('wallet.deposits.return', ['deposit' => $depositId]),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (GeniusPayRequestFailedException) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'payment_provider_unavailable',
            ], 503);
        }

        return new JsonResponse([
            'deposit_id' => $deposit->id,
            'state' => $deposit->state->value,
            'checkout_url' => $deposit->checkout_url,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
