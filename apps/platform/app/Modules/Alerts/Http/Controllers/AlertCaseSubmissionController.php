<?php

namespace App\Modules\Alerts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Http\Requests\StoreCommunityCaseRequest;
use App\Modules\Alerts\Services\CaseSubmissionService;
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
 * Déclaration communautaire (UX-0001 §20 ; ecosystem/alertes/02 §2).
 * Gouverné par `alert_case.submit` (portée `self`), inclus dans `user.base`
 * dès l'inscription.
 *
 * Propose puis soumet en un seul appel HTTP (draft → submitted) : le
 * formulaire mobile ne présente pas d'étape brouillon distincte dans ce
 * lot ; les deux transitions restent néanmoins deux événements distincts
 * dans `alerts.case_events`, jamais fusionnés en un seul.
 */
class AlertCaseSubmissionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CaseSubmissionService $submissionService,
    ) {}

    public function store(StoreCommunityCaseRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;
        $personId = $subject->personAccountLink->person_id;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'alert_case.submit',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'alerts.case',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: $request->string('country_code')->toString(),
                territoryCodes: [],
                environment: $environment,
            ),
            environment: $environment,
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return $this->failureResponder->forOutcome($exception);
        }

        $correlationId = (string) Str::uuid();

        $case = $this->submissionService->proposeCommunityCase(
            author: $subject->personAccountLink,
            category: CaseCategory::from($request->string('category')->toString()),
            sourceDescription: $request->string('source_description')->toString(),
            countryCode: $request->string('country_code')->toString(),
            territoryCode: $request->string('territory_code')->toString() ?: null,
            exactLocation: $request->input('exact_location'),
            recallPhone: $request->string('recall_phone')->toString() ?: null,
            locale: $request->string('locale')->toString(),
            correlationId: $correlationId,
        );

        $case = $this->submissionService->submitCommunityCase($case, $subject->personAccountLink, $correlationId);

        return response()->json([
            'case_id' => $case->id,
            'state' => $case->state,
        ], 201);
    }
}
