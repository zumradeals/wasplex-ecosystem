<?php

namespace App\Modules\Alerts\Http\Controllers\Institutional;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Requests\StoreDispatchDecisionRequest;
use App\Modules\Alerts\Models\InstitutionDispatch;
use App\Modules\Alerts\Services\CaseDispatchService;
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
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Décision institutionnelle sur une transmission (ecosystem/institutions/01
 * §6) : `alert_case.acknowledge`/`.accept`/`.process`/`.resolve`, chacune
 * vérifiée séparément par `AuthorizationGate`, jamais devinée depuis la
 * visibilité de la file (`InstitutionalPortalController`).
 */
class DispatchDecisionController extends Controller
{
    private const CAPABILITY_BY_DECISION = [
        'acknowledge' => 'alert_case.acknowledge',
        'accept' => 'alert_case.accept',
        'process' => 'alert_case.process',
        'resolve' => 'alert_case.resolve',
        'refuse' => 'alert_case.acknowledge',
    ];

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CaseDispatchService $dispatchService,
    ) {}

    public function store(StoreDispatchDecisionRequest $request, InstitutionDispatch $dispatch): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        // Un grant institutionnel est porté par une `Membership`, jamais
        // par la seule liaison personne-compte (ecosystem/institutions/01
        // §3) : l'`AuthorizationEngine` n'examine les grants scopés
        // `membership_id` que si la requête revendique explicitement cette
        // appartenance (P003-B1 §4) — jamais devinée depuis l'organisation
        // du dossier, toujours vérifiée par un second appel au résolveur.
        $membership = Membership::query()
            ->where('person_account_link_id', $subject->personAccountLink->id)
            ->where('organization_id', $dispatch->organization_id)
            ->where('status', MembershipStatus::Active)
            ->first();

        if ($membership !== null) {
            try {
                $subject = $this->subjectResolver->resolve($request, $membership->id);
            } catch (SubjectResolutionFailedException $exception) {
                return $this->failureResponder->forUnresolvedSubject($exception);
            }
        }

        $decision = $request->string('decision')->toString();
        $capabilityKey = self::CAPABILITY_BY_DECISION[$decision];
        $environment = Environment::tryFrom(app()->environment()) ?? Environment::Production;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: $capabilityKey,
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'alerts.institution_dispatch',
                resourceId: $dispatch->id,
                organizationId: $dispatch->organization_id,
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

        $actor = $subject->personAccountLink;
        $organization = $dispatch->organization;
        $correlationId = (string) Str::uuid();

        $dispatch = match ($decision) {
            'acknowledge' => $this->dispatchService->acknowledge($dispatch, $actor, $organization, $correlationId),
            'accept' => $this->dispatchService->accept($dispatch, $actor, $organization, $correlationId),
            'process' => $this->dispatchService->process($dispatch, $actor, $organization, $correlationId),
            'resolve' => $this->dispatchService->resolve($dispatch, $actor, $organization, $correlationId),
            'refuse' => $this->dispatchService->refuse($dispatch, $actor, $organization, (string) $request->string('reason'), $correlationId),
            default => throw new InvalidCaseTransitionException("décision inconnue : {$decision}"),
        };

        return response()->json(['dispatch_id' => $dispatch->id, 'state' => $dispatch->state->value]);
    }
}
