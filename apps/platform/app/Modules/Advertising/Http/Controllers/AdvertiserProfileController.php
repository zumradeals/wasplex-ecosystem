<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreAdvertiserProfileRequest;
use App\Modules\Advertising\Services\AdvertiserOnboardingService;
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
 * Déclaration par une personne authentifiée de son propre dossier
 * annonceur (P007) — première porte d'entrée réelle du parcours
 * annonceur : sans elle, aucun compte ne peut jamais atteindre
 * `campaign.create` (portée `self`, exigeant un `AdvertiserProfile`
 * existant dont on est le représentant).
 *
 * Même discipline que {@see CampaignController} : aucune nouvelle logique
 * d'autorisation ici, seule composition des briques déjà vérifiées de
 * `Governance\Authorization\Integration` et du service Advertising dédié.
 */
class AdvertiserProfileController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly AdvertiserOnboardingService $onboardingService,
    ) {}

    public function store(StoreAdvertiserProfileRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'advertiser_profile.create',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.advertiser_profile',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $subject->personAccountLink->person_id,
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

        $profile = $this->onboardingService->registerAdvertiser(
            representative: $subject->personAccountLink,
            legalName: $request->validated('legal_name'),
            legalIdentifier: $request->validated('legal_identifier'),
            countryCode: $request->validated('country_code'),
            territories: $request->validated('territories'),
        );

        return response()->json([
            'advertiser_profile_id' => $profile->id,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
