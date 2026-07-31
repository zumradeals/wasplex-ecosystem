<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Requests\StoreAdvertiserWalletAllocationRequest;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\AdvertiserWalletService;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
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
 * Allocation du solde Wallet annonceur vers le budget disponible d'une
 * campagne précise (instruction explicite du fondateur, 2026-07-31).
 * `advertiser_wallet.allocate`, portée self — vérifié deux fois : le dossier
 * annonceur possédant le Wallet ET la campagne ciblée doivent appartenir au
 * même représentant authentifié (`Campaign::advertiser_profile_id` doit
 * correspondre au dossier résolu ci-dessous), jamais transmis tel quel par
 * le client.
 */
class AdvertiserWalletAllocationController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly AdvertiserWalletService $walletService,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    public function store(StoreAdvertiserWalletAllocationRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $advertiser = AdvertiserProfile::query()
            ->where('representative_person_account_link_id', $subject->personAccountLink->id)
            ->first();

        if ($advertiser === null) {
            return new JsonResponse(['decision' => 'denied', 'reason' => 'no_advertiser_profile'], 404);
        }

        $campaign = Campaign::query()
            ->where('id', $request->validated('campaign_id'))
            ->where('advertiser_profile_id', $advertiser->id)
            ->first();

        if ($campaign === null) {
            // Existe mais appartient à un autre dossier, ou n'existe pas :
            // même réponse dans les deux cas — jamais confirmer à l'appelant
            // l'existence d'une campagne qui ne lui appartient pas.
            return new JsonResponse(['decision' => 'denied', 'reason' => 'campaign_not_found'], 404);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'advertiser_wallet.allocate',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.advertiser_wallet',
                resourceId: $advertiser->id,
                organizationId: null,
                ownerPersonId: $advertiser->representative->person_id,
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
            $transaction = $this->walletService->allocateToCampaign(
                $advertiser,
                $campaign,
                (int) $request->validated('amount'),
                (string) $request->validated('idempotency_key'),
                (string) Str::uuid(),
            );
        } catch (InsufficientBudgetException) {
            return new JsonResponse(['decision' => 'denied', 'reason' => 'insufficient_wallet_balance'], 422);
        } catch (IdempotencyConflictException) {
            return new JsonResponse(['decision' => 'denied', 'reason' => 'idempotency_key_conflict'], 409);
        }

        return new JsonResponse([
            'campaign_id' => $campaign->id,
            'allocation_transaction_id' => $transaction->id,
            'campaign_available' => $this->budgetProjection->available($campaign->fresh()),
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
