<?php

namespace App\Modules\Alerts\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Requests\StoreCaseDecisionRequest;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Services\CaseModerationService;
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
 * Décision de modération sur un dossier `community` (mission P008-A §17) :
 * `alert_case.review` pour la revue/restriction/refus,
 * `alert_case.publish` pour la publication — deux capacités distinctes,
 * chacune vérifiée séparément.
 */
class AdminCaseDecisionController extends Controller
{
    private const CAPABILITY_BY_DECISION = [
        'start_review' => 'alert_case.review',
        'restrict' => 'alert_case.review',
        'reject' => 'alert_case.review',
        'publish' => 'alert_case.publish',
    ];

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CaseModerationService $moderationService,
    ) {}

    public function store(StoreCaseDecisionRequest $request, AlertCase $case): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $decision = $request->string('decision')->toString();
        $capabilityKey = self::CAPABILITY_BY_DECISION[$decision] ?? 'alert_case.review';
        $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: $capabilityKey,
            operation: Operation::Write,
            resource: new ResourceContext(
                // Doit correspondre exactement au `resource_type` documenté
                // par les migrations `alert_case.review`/`.publish`
                // (portée système entière, jamais scopée à un dossier
                // précis) — un grant scopé `resource_type` exige une
                // correspondance exacte pour que l'engine le retienne.
                resourceType: 'alerts.case_category',
                resourceId: $case->id,
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

        $case = match ($decision) {
            'start_review' => $this->moderationService->startReview($case, $reviewer, $correlationId),
            'publish' => $this->moderationService->publish(
                $case,
                $reviewer,
                (string) $request->string('title'),
                (string) $request->string('summary'),
                $request->string('approximate_zone')->toString() ?: null,
                [],
                $correlationId,
            ),
            'restrict' => $this->moderationService->restrict($case, $reviewer, (string) $request->string('reason'), $correlationId),
            'reject' => $this->moderationService->reject($case, $reviewer, (string) $request->string('reason'), $correlationId),
            default => throw new InvalidCaseTransitionException("décision inconnue : {$decision}"),
        };

        return response()->json(['case_id' => $case->id, 'state' => $case->state]);
    }
}
