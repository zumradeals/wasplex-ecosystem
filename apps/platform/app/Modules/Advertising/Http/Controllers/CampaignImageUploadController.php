<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreCampaignImageRequest;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Services\CampaignImageUploadService;
use App\Modules\Advertising\Services\Exceptions\ImageOrientationRefusedException;
use App\Modules\Advertising\Services\Exceptions\ImageUnreadableException;
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
 * Upload d'une image publicitaire avant création de campagne (Lot 6,
 * instruction explicite du fondateur 2026-07-30) — mirroir d'autorisation
 * exact de {@see CampaignVideoUploadController} (même capacité
 * `campaign.create` : un geste préparatoire du même parcours, jamais une
 * écriture de campagne). Ne rattache jamais le fichier à une campagne ici
 * — le chemin retourné est simplement inclus dans `creations` par le
 * formulaire au moment de la soumission réelle
 * ({@see CampaignController::store()}).
 */
class CampaignImageUploadController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignImageUploadService $uploadService,
    ) {}

    public function store(StoreCampaignImageRequest $request): JsonResponse
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

        try {
            $result = $this->uploadService->store($request->file('image'));
        } catch (ImageOrientationRefusedException $exception) {
            return response()->json([
                'reason' => 'image_orientation_refused',
                'message' => $exception->getMessage(),
            ], 422);
        } catch (ImageUnreadableException $exception) {
            return response()->json([
                'reason' => 'image_unreadable',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result, 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
