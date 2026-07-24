<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreCampaignRequest;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use App\Modules\Advertising\Services\CampaignService;
use App\Modules\Advertising\Services\CampaignVersionService;
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
 * Première route sensible réelle de l'écosystème (P005-B) : soumission
 * d'une campagne en draft par son auteur. Ne construit ni approbation, ni
 * modération, ni diffusion (ADR-0010 §5, §8) — seule la création de
 * `Campaign` + première `CampaignVersion` à l'état `draft`.
 *
 * Le contrôleur ne fait que composer les briques déjà vérifiées de
 * `Governance\Authorization\Integration` (P003-B2/B4) et les services
 * Advertising déjà existants (`CampaignService`, `CampaignVersionService`,
 * `AudienceSegmentGuard`, P005-A) — aucune nouvelle logique d'autorisation,
 * aucune écriture directe dans `advertising.*` hors de ces services.
 */
class CampaignController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignService $campaignService,
        private readonly CampaignVersionService $campaignVersionService,
        private readonly AudienceSegmentGuard $audienceSegmentGuard,
    ) {}

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        // Le dossier annonceur est déjà vérifié existant et actif par
        // StoreCampaignRequest ; son représentant réellement persisté —
        // jamais l'identifiant transmis par le client — est l'unique
        // source de vérité de la ressource visée (même invariant que
        // AuthenticatedSubjectResolver pour une appartenance revendiquée).
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

        [$campaign, $version] = DB::transaction(function () use ($request, $advertiserProfile, $subject) {
            $campaign = $this->campaignService->createCampaign(
                $advertiserProfile,
                $request->validated('code'),
                $request->validated('currency'),
            );

            $sector = SectorClassification::query()->where('id', $request->validated('sector_classification_id'))->firstOrFail();

            $version = $this->campaignVersionService->propose(
                campaign: $campaign,
                sector: $sector,
                creations: $request->validated('creations'),
                expectedEvent: $request->validated('expected_event'),
                destination: $request->validated('destination'),
                territory: $request->validated('territory'),
                author: $subject->personAccountLink,
                pricingConfigurationKey: $request->validated('pricing_configuration_key'),
                pricingConfigurationVersion: $request->validated('pricing_configuration_version'),
            );

            $this->audienceSegmentGuard->createSegment(
                $version,
                $request->validated('audience.criteria'),
                $request->validated('audience.estimated_size'),
            );

            return [$campaign, $version];
        });

        return response()->json([
            'campaign_id' => $campaign->id,
            'campaign_version_id' => $version->id,
            'state' => $version->state->value,
        ], 201);
    }

    /**
     * `Environment` est une dimension de portée du moteur d'autorisation
     * (ADR-0004 §7), jamais devinée : elle reflète l'environnement Laravel
     * réel. Une valeur d'environnement Laravel non prévue par l'enum échoue
     * fermée vers `Production` plutôt que vers une valeur plus permissive
     * (ADR-0004 §2 « le refus est la règle par défaut »).
     */
    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
