<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreQualifiedEventRejectionRequest;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Services\CampaignBudgetService;
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

/**
 * Neuvième route sensible réelle de l'écosystème (P005-F) : refus d'un
 * `QualifiedEvent` `Pending` par contre-écriture explicite de sa
 * réservation (ADR-0010 §4 ligne 5 ; ADR-0003 §11).
 *
 * Réservée au même personnel que `event.accept` — voir le raisonnement
 * documenté sur `event.reject` (migration `2026_07_25_100011`).
 *
 * `CampaignBudgetService::rejectQualifiedEvent()` (P005-A, déjà écrit et
 * testé) reste l'unique décideur métier ; il rejoue sans second effet si
 * l'événement est déjà résolu.
 */
class QualifiedEventRejectionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignBudgetService $campaignBudgetService,
    ) {}

    public function store(StoreQualifiedEventRejectionRequest $request, QualifiedEvent $qualifiedEvent): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'event.reject',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.qualified_event',
                resourceId: $qualifiedEvent->id,
                organizationId: null,
                ownerPersonId: $qualifiedEvent->beneficiary->person_id,
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

        $event = $this->campaignBudgetService->rejectQualifiedEvent($qualifiedEvent, $request->validated('reason'));

        return response()->json([
            'qualified_event_id' => $event->id,
            'billing_status' => $event->billing_status->value,
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
