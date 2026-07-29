<?php

namespace App\Modules\Alerts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Requests\StoreCorrespondenceDecisionRequest;
use App\Modules\Alerts\Models\CorrespondenceReport;
use App\Modules\Alerts\Services\CorrespondenceService;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
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
 * Validation humaine d'une correspondance (`alert_match.validate`) — AMD-0007
 * §9 : « une correspondance automatisée reste une hypothèse et ne décide
 * pas seule d'un dossier humain sensible ».
 */
class AdminCorrespondenceDecisionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CorrespondenceService $correspondenceService,
    ) {}

    public function store(StoreCorrespondenceDecisionRequest $request, CorrespondenceReport $correspondenceReport): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'alert_match.validate',
            operation: Operation::Write,
            resource: new ResourceContext(
                // Doit correspondre exactement au `resource_type` documenté
                // par la migration `alert_match.validate` (portée système
                // entière, jamais scopée à un dossier précis).
                resourceType: 'alerts.case_category',
                resourceId: $correspondenceReport->id,
                organizationId: null,
                ownerPersonId: null,
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

        $reviewer = $subject->personAccountLink;
        $correlationId = (string) Str::uuid();
        $decision = $request->string('decision')->toString();

        $correspondenceReport = match ($decision) {
            'validate' => $this->correspondenceService->validate($correspondenceReport, $reviewer, $correlationId),
            'reject' => $this->correspondenceService->reject($correspondenceReport, $reviewer, $correlationId),
            default => throw new InvalidCaseTransitionException("décision inconnue : {$decision}"),
        };

        return response()->json(['correspondence_report_id' => $correspondenceReport->id, 'review_state' => $correspondenceReport->review_state->value]);
    }
}
