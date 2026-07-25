<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Http\Requests\StoreQualifiedEventRequest;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\Exceptions\CampaignNotAcceptingReservationsException;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Septième route sensible réelle de l'écosystème (P005-F) : réservation du
 * coût applicable et création d'un `QualifiedEvent` (ADR-0010 §4 ligne 3 ;
 * `01-cycle-creation-valeur.md` §3). Réservée au personnel Wasplex
 * anti-fraude/mesure d'attention, jamais au bénéficiaire lui-même — voir le
 * raisonnement documenté sur `event.submit` (migration `2026_07_25_100009`).
 *
 * `CampaignBudgetService::submitQualifiedEvent()` (P005-A, déjà écrit et
 * testé) reste l'unique décideur métier.
 */
class QualifiedEventSubmissionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignBudgetService $campaignBudgetService,
    ) {}

    public function store(StoreQualifiedEventRequest $request, CampaignVersion $campaignVersion): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $campaign = $campaignVersion->campaign;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'event.submit',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: $campaign->id,
                organizationId: null,
                ownerPersonId: $campaign->advertiserProfile->representative->person_id,
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

        // Aucun événement n'est jamais réservé contre une version qui n'a
        // pas franchi la revue humaine indépendante (ADR-0010 §2 : « ne
        // diffuse rien sans version approuvée immuable ») — vérification à
        // la porte, `CampaignBudgetService::submitQualifiedEvent()`
        // lui-même ne la fait pas (P005-A ne la lui a jamais demandée).
        if ($campaignVersion->state !== CampaignVersionState::Approved) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'campaign_version_not_approved',
            ], 409);
        }

        $beneficiary = PersonAccountLink::query()
            ->where('id', $request->validated('beneficiary_person_account_link_id'))
            ->firstOrFail();

        try {
            $event = $this->campaignBudgetService->submitQualifiedEvent(
                campaign: $campaign,
                version: $campaignVersion,
                beneficiary: $beneficiary,
                format: $request->validated('format'),
                evidence: $request->validated('evidence'),
                appliedPriceAmount: $request->validated('applied_price_amount'),
                idempotencyKey: $request->validated('idempotency_key'),
                correlationId: (string) Str::uuid(),
                fraudDecision: $request->validated('fraud_decision') !== null
                    ? FraudDecision::from($request->validated('fraud_decision'))
                    : FraudDecision::None,
                pricingConfigurationKey: $request->validated('pricing_configuration_key'),
                pricingConfigurationVersion: $request->validated('pricing_configuration_version'),
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
