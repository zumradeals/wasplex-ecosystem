<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Projections\SocialEngagementProjection;
use App\Modules\Advertising\Services\SocialEngagementService;
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
 * Bascule un « j'aime » sur une publicité (Lot 3 Phase A, menu vertical du
 * Feed, décision de Koné 2026-07-26 — `campaign_version.like`). Signal
 * social pur : ne crée, ne touche et n'importe aucun composant Wallet ou
 * Ledger (voir {@see SocialEngagementService}).
 *
 * Portée large (comme `campaign.report`) : n'importe quel utilisateur
 * authentifié habilité peut aimer n'importe quelle publicité diffusée, pas
 * seulement les siennes.
 */
class CampaignVersionLikeController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly SocialEngagementService $socialEngagementService,
        private readonly SocialEngagementProjection $socialEngagementProjection,
    ) {}

    public function store(Request $request, CampaignVersion $campaignVersion): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign_version.like',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign_version',
                resourceId: $campaignVersion->id,
                organizationId: null,
                ownerPersonId: $campaignVersion->campaign->advertiserProfile->representative->person_id,
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

        $liked = $this->socialEngagementService->toggleLike($campaignVersion, $subject->personAccountLink);
        $counts = $this->socialEngagementProjection->counts($campaignVersion->id);

        return response()->json([
            'liked' => $liked,
            'likes_count' => $counts['likes'],
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
