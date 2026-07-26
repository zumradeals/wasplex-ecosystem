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
 * Enregistre une intention de partage d'une publicité (Lot 3 Phase A,
 * décision de Koné 2026-07-26 — `campaign_version.share`). Le partage
 * effectif se produit hors plateforme : cette route ne fait que compter le
 * geste, jamais le destinataire (AMD-0001, AMD-0009). Signal social pur,
 * aucun effet financier.
 *
 * Contrairement au like/favori, chaque appel crée un nouvel événement —
 * jamais une bascule (partager plusieurs fois est légitime).
 */
class CampaignVersionShareController extends Controller
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
            capabilityKey: 'campaign_version.share',
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

        $this->socialEngagementService->recordShare($campaignVersion, $subject->personAccountLink);
        $counts = $this->socialEngagementProjection->counts($campaignVersion->id);

        return response()->json([
            'shares_count' => $counts['shares'],
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
