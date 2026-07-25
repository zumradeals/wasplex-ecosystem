<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreCampaignFundingRequest;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Ledger\Services\Exceptions\IdempotencyConflictException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Sixième route sensible réelle de l'écosystème (P005-E) : enregistrement
 * d'un encaissement confirmé d'annonceur (ADR-0010 §4 ligne 1 ;
 * ADR-0003 §7-8 « préfinancement »). Première route de tout l'écosystème à
 * produire réellement une écriture Ledger équilibrée.
 *
 * Portée volontairement réservée au personnel finance Wasplex, jamais à
 * l'annonceur lui-même — voir le raisonnement documenté sur
 * `campaign.fund` (migration `2026_07_25_100008`).
 *
 * `CampaignBudgetService::fund()` (P005-A, déjà écrit et testé) reste
 * l'unique décideur métier ; ce contrôleur ne fait que composer les briques
 * déjà vérifiées de `Governance\Authorization\Integration`, sur le modèle
 * exact des contrôleurs précédents.
 */
class CampaignFundingController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignBudgetService $campaignBudgetService,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    public function store(StoreCampaignFundingRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.fund',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: $campaign->id,
                organizationId: null,
                ownerPersonId: $campaign->advertiserProfile->representative->person_id,
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
            $transaction = $this->campaignBudgetService->fund(
                $campaign,
                $request->validated('amount'),
                $request->validated('funding_reference'),
                (string) Str::uuid(),
            );
        } catch (IdempotencyConflictException) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'funding_reference_conflict',
            ], 409);
        }

        return response()->json([
            'campaign_id' => $campaign->id,
            'funding_transaction_id' => $transaction->id,
            'available' => $this->budgetProjection->available($campaign->fresh()),
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
