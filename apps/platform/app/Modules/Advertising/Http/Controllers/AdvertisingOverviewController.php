<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Balance\Http\Controllers\WalletOverviewController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tableau de bord annonceur (P007-W1, `A-001-01`/`A-002-01`) : consultation
 * par un représentant de ses propres campagnes et de leur budget projeté
 * — voir `campaign.view` (migration `2026_07_25_100013`), portée
 * exclusivement `self`.
 *
 * Même discipline que {@see WalletOverviewController} :
 * un refus d'autorisation restitue un état d'écran, jamais une erreur JSON
 * brute (CLAUDE.md §6). L'absence de dossier annonceur n'est pas un refus
 * d'autorisation — `campaign.view` reste accordée (TD-0005-D, octroi
 * automatique `user.base`) — mais un état d'écran distinct invitant à
 * déclarer un dossier ({@see AdvertiserProfileController}).
 */
class AdvertisingOverviewController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    public function index(Request $request): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render('advertising/overview', [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'advertiserProfile' => null,
                'campaigns' => [],
                'sectorClassifications' => [],
            ]);
        }

        $personId = $subject->personAccountLink->person_id;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'campaign.view',
            operation: Operation::Read,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: null,
                organizationId: null,
                ownerPersonId: $personId,
                countryCode: null,
                territoryCodes: [],
                environment: $this->currentEnvironment(),
            ),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return Inertia::render('advertising/overview', [
                'access' => ['allowed' => false, 'reason' => $exception->result->reason->code],
                'advertiserProfile' => null,
                'campaigns' => [],
                'sectorClassifications' => [],
            ]);
        }

        $advertiserProfile = AdvertiserProfile::query()
            ->where('representative_person_account_link_id', $subject->personAccountLink->id)
            ->first();

        if ($advertiserProfile === null) {
            return Inertia::render('advertising/overview', [
                'access' => ['allowed' => true, 'reason' => null],
                'advertiserProfile' => null,
                'campaigns' => [],
                'sectorClassifications' => [],
            ]);
        }

        $sectorClassifications = SectorClassification::query()
            ->where('state', 'active')
            ->orderBy('sector')
            ->get(['id', 'country_code', 'sector']);

        $campaigns = Campaign::query()
            ->where('advertiser_profile_id', $advertiserProfile->id)
            ->with(['availableAccount', 'reservedAccount', 'consumedAccount', 'versions' => function ($query): void {
                $query->orderByDesc('version');
            }])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('advertising/overview', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => [
                'id' => $advertiserProfile->id,
                'legal_name' => $advertiserProfile->legal_name,
                'status' => $advertiserProfile->status->value,
            ],
            'campaigns' => $campaigns->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'code' => $campaign->code,
                'currency' => $campaign->currency,
                'state' => $campaign->state->value,
                'latest_version_id' => $campaign->versions->first()?->id,
                'latest_version_state' => $campaign->versions->first()?->state->value,
                'budget' => [
                    'available' => $this->budgetProjection->available($campaign),
                    'reserved' => $this->budgetProjection->reserved($campaign),
                    'consumed' => $this->budgetProjection->consumed($campaign),
                ],
            ])->all(),
            'sectorClassifications' => $sectorClassifications->map(fn (SectorClassification $sector): array => [
                'id' => $sector->id,
                'label' => "{$sector->country_code} — {$sector->sector}",
            ])->all(),
        ]);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
