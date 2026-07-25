<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Http\Requests\StoreSelfQualifiedEventRequest;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\Exceptions\CampaignNotAcceptingReservationsException;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Auto-soumission par le bénéficiaire de sa propre preuve d'attention
 * qualifiée (W2, `event.self_submit`) — ferme la dette documentée sur
 * `event.submit` (migration `2026_07_25_100009`, TD-0004) : le prix est
 * désormais résolu par {@see QualifiedEventPricingResolver} via le registre
 * Configuration, jamais transmis par l'appelant, ce qui rend enfin ce flux
 * sûr en libre-service.
 *
 * Distincte et sans effet sur
 * {@see QualifiedEventSubmissionController} (personnel Wasplex, portée
 * large, montant pré-vérifié hors système) : les deux routes coexistent,
 * la seconde restant un outil de dérogation anti-fraude.
 */
class QualifiedEventSelfSubmissionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly QualifiedEventPricingResolver $pricingResolver,
        private readonly CampaignBudgetService $campaignBudgetService,
    ) {}

    public function store(StoreSelfQualifiedEventRequest $request, CampaignVersion $campaignVersion): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $campaign = $campaignVersion->campaign;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'event.self_submit',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: $campaign->id,
                organizationId: null,
                // Portée self : le bénéficiaire réel de cet événement est
                // toujours le sujet authentifié lui-même — jamais le
                // représentant de l'annonceur (à la différence de
                // QualifiedEventSubmissionController, dont la portée large
                // ne s'appuie jamais sur ownerPersonId).
                ownerPersonId: $subject->personAccountLink->person_id,
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

        if ($campaignVersion->state !== CampaignVersionState::Approved) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'campaign_version_not_approved',
            ], 409);
        }

        try {
            $appliedPriceAmount = $this->pricingResolver->resolveBasePrice($campaignVersion);
        } catch (PricingConfigurationNotResolvableException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'pricing_not_resolvable',
            ], 409);
        }

        try {
            $event = $this->campaignBudgetService->submitQualifiedEvent(
                campaign: $campaign,
                version: $campaignVersion,
                beneficiary: $subject->personAccountLink,
                format: $request->validated('format'),
                evidence: $request->validated('evidence'),
                appliedPriceAmount: $appliedPriceAmount,
                idempotencyKey: $request->validated('idempotency_key'),
                correlationId: (string) Str::uuid(),
                fraudDecision: FraudDecision::None,
                pricingConfigurationKey: $campaignVersion->pricing_configuration_key,
                pricingConfigurationVersion: $campaignVersion->pricing_configuration_version,
            );
        } catch (CampaignNotAcceptingReservationsException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'campaign_not_accepting_reservations',
            ], 409);
        } catch (InsufficientBudgetException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'insufficient_budget',
            ], 409);
        }

        return response()->json([
            'qualified_event_id' => $event->id,
            'billing_status' => $event->billing_status->value,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
