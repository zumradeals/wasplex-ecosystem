<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\EstimateAudienceRequest;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Services\AudienceCriteria;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use App\Modules\Advertising\Services\Exceptions\ForbiddenTargetingCriterionException;
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
 * Aperçu en direct de la taille d'audience, avant création de campagne
 * (Lot 3, véto du dirigeant) — mirroir d'autorisation exact de
 * {@see CampaignController::store()} (même capacité `campaign.create` :
 * ceci n'est qu'un calcul de prévisualisation du même geste, jamais une
 * écriture). Ne crée jamais de segment ni de campagne.
 */
class AudienceEstimateController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly AudienceSegmentGuard $audienceSegmentGuard,
    ) {}

    public function store(EstimateAudienceRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $advertiserProfile = AdvertiserProfile::query()->where('id', $request->validated('advertiser_profile_id'))->firstOrFail();

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.create',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: null,
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

        $criteria = $request->validated('criteria');

        try {
            AudienceCriteria::assertAllowed($criteria);
        } catch (ForbiddenTargetingCriterionException $exception) {
            return response()->json([
                'decision' => 'denied',
                'reason' => 'forbidden_targeting_criterion',
            ], 422);
        }

        return response()->json($this->audienceSegmentGuard->estimateForPreview($criteria));
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
