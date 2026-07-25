<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\ModerationDecision;
use App\Modules\Advertising\Enums\PrecautionaryMeasure;
use App\Modules\Advertising\Http\Requests\StoreModerationDecisionRequest;
use App\Modules\Advertising\Models\ModerationCase;
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
use Illuminate\Support\Facades\DB;

/**
 * Cinquième route sensible réelle de l'écosystème (P005-D) : décision de
 * modération sur un `ModerationCase` déjà ouvert (`03-...md` §2 ;
 * ADR-0010 §3), capacité distincte et plus sensible que `campaign.report`
 * — voir le raisonnement de `risk_class = sensitive` documenté sur
 * `campaign.moderate` (migration `2026_07_25_100007`), y compris le risque
 * ouvert (non tranché par ce lot) qu'un signaleur détenant aussi un grant
 * `campaign.moderate` puisse juger son propre signalement.
 *
 * `ModerationService::recordDecision()` clôt toujours le dossier
 * (`status = resolved`) ; `applyPrecautionaryMeasure()` n'est appelée que
 * si une mesure est explicitement transmise, et c'est elle seule qui, pour
 * `campaign_suspended`, fait réellement transiter `Campaign.state` vers
 * `Suspended` (P005-A, déjà écrit et déjà exercée par `CampaignSuspensionTest`
 * — aucune nouvelle méthode de suspension n'est ajoutée à `CampaignService`
 * par ce lot : le précédent existant est réutilisé tel quel).
 */
class ModerationCaseDecisionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly ModerationService $moderationService,
    ) {}

    public function store(StoreModerationDecisionRequest $request, ModerationCase $moderationCase): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.moderate',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.moderation_case',
                resourceId: $moderationCase->id,
                organizationId: null,
                ownerPersonId: null,
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

        $measure = $request->validated('precautionary_measure') !== null
            ? PrecautionaryMeasure::from($request->validated('precautionary_measure'))
            : null;

        $case = DB::transaction(function () use ($request, $moderationCase, $measure): ModerationCase {
            $case = $this->moderationService->recordDecision(
                $moderationCase,
                ModerationDecision::from($request->validated('decision')),
            );

            if ($measure !== null) {
                $case = $this->moderationService->applyPrecautionaryMeasure($case, $measure);
            }

            return $case;
        });

        return response()->json([
            'moderation_case_id' => $case->id,
            'status' => $case->status->value,
            'decision' => $case->decision->value,
            'precautionary_measure' => $case->precautionary_measure->value,
            'campaign_state' => $case->campaign->fresh()->state->value,
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
