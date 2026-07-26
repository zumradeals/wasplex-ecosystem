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
 * Bascule un favori sur une publicité (Lot 3 Phase A, décision de Koné
 * 2026-07-26 — `campaign_version.favorite`). Même discipline exacte que
 * {@see CampaignVersionLikeController} : signal social pur, portée large.
 */
class CampaignVersionFavoriteController extends Controller
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
            capabilityKey: 'campaign_version.favorite',
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

        $favorited = $this->socialEngagementService->toggleFavorite($campaignVersion, $subject->personAccountLink);
        $counts = $this->socialEngagementProjection->counts($campaignVersion->id);

        return response()->json([
            'favorited' => $favorited,
            'favorites_count' => $counts['favorites'],
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
