<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\CampaignVersionService;
use App\Modules\Advertising\Services\Exceptions\CampaignVersionNotApprovableException;
use App\Modules\Advertising\Services\Exceptions\IndependentApproverRequiredException;
use App\Modules\Advertising\Services\Exceptions\SelfApprovalRefusedException;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Governance\Authorization\Support\ConditionsMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Troisième route sensible réelle de l'écosystème (P005-C) : approbation
 * d'une CampaignVersion en `in_review` (ADR-0010 §3, §5).
 *
 * La séparation des tâches (ADR-0004 §12, ADR-0010 §5) n'est PAS exprimée
 * ici par une condition du moteur d'autorisation :
 * {@see ConditionsMatcher}
 * (P003-B1 §11) ne compare que des attributs du sujet à des seuils déclarés,
 * jamais un attribut du sujet à un attribut d'une ressource métier précise
 * (ici, l'auteur réel de CETTE version). `AuthorizationGate` vérifie
 * seulement qu'un grant `campaign.approve` actif couvre le dossier
 * annonceur visé (portée `self` ou `resource_ids`, ADR-0004 §7) — la
 * décision finale, elle, reste entièrement portée par
 * {@see CampaignVersionService::approve()} (P005-A, déjà écrit et testé),
 * qui refuse inconditionnellement `approver === author` (renforcé par la
 * contrainte SQL `campaign_versions_author_not_approver_check`) et exige un
 * approbateur non nul pour les secteurs à revue renforcée (ADR-0004 §11 :
 * « le module propriétaire de la ressource conserve la décision métier
 * finale »).
 *
 * Le sujet authentifié réel est toujours transmis comme approbateur —
 * jamais `null` : contrairement aux tests directs de
 * {@see CampaignVersionService} (P005-A), qui exercent le service isolément,
 * une requête HTTP authentifiée a toujours une personne réelle derrière
 * elle ; `null` n'a de sens qu'en l'absence de tout appelant identifiable.
 */
class CampaignVersionApprovalController extends Controller
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

        $advertiserProfile = $campaignVersion->campaign->advertiserProfile;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.approve',
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
            $version = $this->campaignVersionService->approve($campaignVersion, $subject->personAccountLink);
        } catch (SelfApprovalRefusedException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'self_approval_refused',
            ], 403);
        } catch (IndependentApproverRequiredException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'independent_approver_required',
            ], 403);
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
