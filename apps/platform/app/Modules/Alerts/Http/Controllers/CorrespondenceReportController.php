<?php

namespace App\Modules\Alerts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Requests\StoreCorrespondenceReportRequest;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Services\CorrespondenceService;
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
 * Signalement d'une correspondance sur un dossier publié
 * (ecosystem/alertes/02 §7). Gouverné par `alert_match.propose` (portée
 * `self`), inclus dans `user.base`.
 */
class CorrespondenceReportController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CorrespondenceService $correspondenceService,
    ) {}

    public function store(StoreCorrespondenceReportRequest $request, AlertCase $case): JsonResponse
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
            capabilityKey: 'alert_match.propose',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'alerts.correspondence_report',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: null,
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

        $report = $this->correspondenceService->report(
            case: $case,
            reporter: $subject->personAccountLink,
            nonPublicDescription: $request->string('non_public_description')->toString(),
            verificationResponse: $request->array('verification_response'),
            correlationId: (string) Str::uuid(),
        );

        return response()->json(['correspondence_report_id' => $report->id], 201);
    }
}
