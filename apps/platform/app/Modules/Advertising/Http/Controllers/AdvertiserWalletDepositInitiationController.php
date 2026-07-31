<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GeniusPayWebhookController;
use App\Modules\Advertising\Http\Requests\StoreAdvertiserWalletDepositInitiationRequest;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Services\AdvertiserWalletDepositInitiationService;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Dépôt Wallet annonceur en libre-service (instruction explicite du
 * fondateur, 2026-07-31 ; mirroir exact de
 * {@see CampaignFundingInitiationController}). Initie un dépôt
 * (`advertiser_wallet.deposit`, portée self — un annonceur ne recharge
 * jamais le Wallet d'un autre dossier) et retourne l'URL de checkout
 * GeniusPay : ce contrôleur ne crédite jamais lui-même de valeur, seul le
 * webhook signé le fait ({@see GeniusPayWebhookController}).
 */
class AdvertiserWalletDepositInitiationController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly AdvertiserWalletDepositInitiationService $initiationService,
    ) {}

    public function store(StoreAdvertiserWalletDepositInitiationRequest $request): JsonResponse
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

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'advertiser_wallet.deposit',
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

        $depositId = (string) Str::uuid7();

        try {
            $deposit = $this->initiationService->initiate(
                depositId: $depositId,
                advertiser: $advertiser,
                currency: strtoupper((string) $request->validated('currency')),
                initiatedByPersonAccountLinkId: $subject->personAccountLink->id,
                amount: (int) $request->validated('amount'),
                successUrl: URL::route('advertising.wallet.deposits.return', ['deposit' => $depositId]),
                errorUrl: URL::route('advertising.wallet.deposits.return', ['deposit' => $depositId]),
                idempotencyKey: (string) $request->validated('idempotency_key'),
            );
        } catch (GeniusPayRequestFailedException) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'payment_provider_unavailable',
            ], 503);
        }

        return new JsonResponse([
            'deposit_id' => $deposit->id,
            'state' => $deposit->state->value,
            'checkout_url' => $deposit->checkout_url,
        ], 201);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
