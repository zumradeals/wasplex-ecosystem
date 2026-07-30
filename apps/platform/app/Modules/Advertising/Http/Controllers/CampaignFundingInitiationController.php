<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GeniusPayWebhookController;
use App\Modules\Advertising\Http\Requests\StoreCampaignFundingInitiationRequest;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Services\CampaignFundingInitiationService;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Deposit\Http\Controllers\DepositInitiationController;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Financement de campagne en libre-service par l'annonceur (véto du
 * dirigeant, 2026-07-30 ; mirroir exact de
 * {@see DepositInitiationController}).
 * Initie un financement (`campaign.fund_self`, portée self — un annonceur ne
 * finance jamais la campagne d'un autre) et retourne l'URL de checkout
 * GeniusPay : ce contrôleur ne crédite jamais lui-même de valeur, seul le
 * webhook signé le fait ({@see GeniusPayWebhookController}).
 *
 * Distinct de `CampaignFundingController` (`campaign.fund`, staff Wasplex,
 * paiements hors GeniusPay confirmés manuellement) — les deux coexistent.
 */
class CampaignFundingInitiationController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignFundingInitiationService $initiationService,
    ) {}

    public function store(StoreCampaignFundingInitiationRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.fund_self',
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

        $campaignFundingId = (string) Str::uuid7();

        try {
            $funding = $this->initiationService->initiate(
                campaignFundingId: $campaignFundingId,
                campaign: $campaign,
                initiatedByPersonAccountLinkId: $subject->personAccountLink->id,
                amount: (int) $request->validated('amount'),
                successUrl: URL::route('advertising.campaigns.self-funding.return', ['campaign' => $campaign->id, 'campaignFunding' => $campaignFundingId]),
                errorUrl: URL::route('advertising.campaigns.self-funding.return', ['campaign' => $campaign->id, 'campaignFunding' => $campaignFundingId]),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (GeniusPayRequestFailedException) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'payment_provider_unavailable',
            ], 503);
        }

        return new JsonResponse([
            'campaign_funding_id' => $funding->id,
            'state' => $funding->state->value,
            'checkout_url' => $funding->checkout_url,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
