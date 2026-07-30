<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignFunding;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Deposit\Http\Controllers\DepositReturnController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page de retour après redirection GeniusPay pour un financement de
 * campagne (mirroir exact de
 * {@see DepositReturnController}).
 * L'état affiché vient toujours d'une lecture fraîche du financement en
 * base, jamais d'un paramètre de requête.
 */
class CampaignFundingReturnController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function show(Request $request, Campaign $campaign, CampaignFunding $campaignFunding): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render('advertising/campaign-funding-return', [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'campaignFunding' => null,
            ]);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.view',
            operation: Operation::Read,
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
            return Inertia::render('advertising/campaign-funding-return', [
                'access' => ['allowed' => false, 'reason' => $exception->result->reason->code],
                'campaignFunding' => null,
            ]);
        }

        return Inertia::render('advertising/campaign-funding-return', [
            'access' => ['allowed' => true, 'reason' => null],
            'campaignFunding' => [
                'id' => $campaignFunding->id,
                'state' => $campaignFunding->state->value,
                'amount' => $campaignFunding->amount,
                'currency' => $campaignFunding->currency,
            ],
        ]);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
