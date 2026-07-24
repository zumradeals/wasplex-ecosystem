<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Advertising\Services\Exceptions\CampaignVersionNotApprovableException;
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
use Illuminate\Http\Request;

/**
 * Deuxième route sensible réelle de l'écosystème (P005-C) : soumission
 * d'une CampaignVersion en `draft` vers `in_review` (ADR-0010 §3, §5).
 *
 * Route + contrôleur séparés de {@see CampaignVersionApprovalController} :
 * la capacité `campaign.submit_for_review` ne couvre jamais l'approbation
 * (`campaign.approve`), sur le modèle exact de la séparation déjà posée
 * entre `campaign.create` et ces deux capacités (P005-B).
 */
class CampaignVersionSubmissionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignVersionService $campaignVersionService,
    ) {}

    public function store(Request $request, CampaignVersion $campaignVersion): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        // Le dossier annonceur réellement propriétaire de cette version —
        // jamais un identifiant transmis par le client, qui n'existe même
        // pas dans cette requête — est résolu depuis la chaîne persistée
        // version → campagne → dossier annonceur (même invariant que
        // CampaignController::store, P005-B).
        $advertiserProfile = $campaignVersion->campaign->advertiserProfile;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.submit_for_review',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.advertiser_profile',
                resourceId: $advertiserProfile->id,
                organizationId: null,
                ownerPersonId: $advertiserProfile->representative->person_id,
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

        try {
            $version = $this->campaignVersionService->submitForReview($campaignVersion);
        } catch (CampaignVersionNotApprovableException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'campaign_version_not_approvable',
            ], 409);
        }

        return response()->json([
            'campaign_version_id' => $version->id,
            'state' => $version->state->value,
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
