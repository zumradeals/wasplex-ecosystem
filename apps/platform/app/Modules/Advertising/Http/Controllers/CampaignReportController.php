<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreModerationReportRequest;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\ModerationService;
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
 * Quatrième route sensible réelle de l'écosystème (P005-D) : signalement
 * d'une campagne par n'importe quel utilisateur authentifié
 * (`03-signalements-sanctions-et-remuneration.md` §1 : « Tout utilisateur
 * peut signaler »), pas seulement un représentant du dossier annonceur
 * visé — voir le raisonnement de portée documenté sur
 * `campaign.report` (migration `2026_07_25_100006`).
 *
 * Le signalement seul n'a aucun effet sur `Campaign`/`CampaignVersion` :
 * {@see ModerationService::openCase()} (P005-A, déjà écrit) se contente de
 * créer le dossier, jamais une mesure conservatoire (§1 : « ne prouve pas
 * seul la violation »).
 */
class CampaignReportController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly ModerationService $moderationService,
    ) {}

    public function store(StoreModerationReportRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.report',
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

        $version = null;

        if ($request->validated('campaign_version_id') !== null) {
            $version = CampaignVersion::query()->where('id', $request->validated('campaign_version_id'))->firstOrFail();
        }

        $case = $this->moderationService->openCase(
            campaign: $campaign,
            reason: $request->validated('reason'),
            severity: $request->validated('severity'),
            version: $version,
            observedDestination: $request->validated('observed_destination'),
        );

        return response()->json([
            'moderation_case_id' => $case->id,
            'status' => $case->status->value,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
