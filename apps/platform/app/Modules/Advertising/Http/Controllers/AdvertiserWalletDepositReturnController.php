<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\AdvertiserWalletDeposit;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page de retour après redirection GeniusPay pour un dépôt Wallet annonceur
 * (instruction explicite du fondateur, 2026-07-31 ; mirroir exact de
 * {@see CampaignFundingReturnController}).
 * L'état affiché vient toujours d'une lecture fraîche du dépôt en base,
 * jamais d'un paramètre de requête.
 */
class AdvertiserWalletDepositReturnController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function show(Request $request, AdvertiserWalletDeposit $deposit): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render('advertising/wallet-deposit-return', [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'deposit' => null,
            ]);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.view',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'advertising.advertiser_wallet',
                resourceId: $deposit->advertiser_profile_id,
                organizationId: null,
                ownerPersonId: $deposit->advertiserProfile->representative->person_id,
                countryCode: null,
                territoryCodes: [],
                environment: $this->currentEnvironment(),
            ),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return Inertia::render('advertising/wallet-deposit-return', [
                'access' => ['allowed' => false, 'reason' => $exception->result->reason->code],
                'deposit' => null,
            ]);
        }

        return Inertia::render('advertising/wallet-deposit-return', [
            'access' => ['allowed' => true, 'reason' => null],
            'deposit' => [
                'id' => $deposit->id,
                'state' => $deposit->state->value,
                'amount' => $deposit->amount,
                'currency' => $deposit->currency,
            ],
        ]);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
