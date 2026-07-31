<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreSubscriptionPurchaseInitiationRequest;
use App\Modules\Advertising\Models\SubscriptionPlan;
use App\Modules\Advertising\Services\SubscriptionPurchaseInitiationService;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Achat d'abonnement en libre-service (instruction explicite du fondateur,
 * 2026-07-31 ; mirroir exact de
 * {@see AdvertiserWalletDepositInitiationController}).
 * `subscription.purchase`, portée self — ne crédite jamais l'abonnement
 * lui-même, seul le webhook signé le fait.
 */
class SubscriptionPurchaseInitiationController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly SubscriptionPurchaseInitiationService $initiationService,
    ) {}

    public function store(StoreSubscriptionPurchaseInitiationRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $plan = SubscriptionPlan::query()
            ->where('id', $request->validated('subscription_plan_id'))
            ->where('state', 'active')
            ->first();

        if ($plan === null) {
            return new JsonResponse(['decision' => 'denied', 'reason' => 'subscription_plan_not_found'], 404);
        }

        $personId = $subject->personAccountLink->person_id;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'subscription.purchase',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.subscription',
                resourceId: null,
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

        $purchaseId = (string) Str::uuid7();

        try {
            $purchase = $this->initiationService->initiate(
                purchaseId: $purchaseId,
                personId: $personId,
                plan: $plan,
                initiatedByPersonAccountLinkId: $subject->personAccountLink->id,
                successUrl: URL::route('subscriptions.purchases.return', ['purchase' => $purchaseId]),
                errorUrl: URL::route('subscriptions.purchases.return', ['purchase' => $purchaseId]),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (GeniusPayRequestFailedException) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'payment_provider_unavailable',
            ], 503);
        }

        return new JsonResponse([
            'purchase_id' => $purchase->id,
            'state' => $purchase->state->value,
            'checkout_url' => $purchase->checkout_url,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
