<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Facturation (UX-0001 §8) : état du financement de chaque campagne
 * (`02-cycle-financier-campagne.md` §2-3) — jamais un formulaire de
 * paiement en libre-service : `campaign.fund` reste « réservé au
 * personnel finance Wasplex, jamais à l'annonceur » (voir le
 * raisonnement documenté sur `routes/web.php`, route
 * `advertising.campaigns.funding.store`). Cet écran explique le
 * processus réel (financement confirmé par Wasplex après réception) et
 * restitue les montants déjà couverts, jamais un solde prépayé
 * inventé.
 */
class AdvertisingBillingController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/billing', [
            'campaignFunding' => [],
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $campaigns = Campaign::query()
            ->where('advertiser_profile_id', $profile->id)
            ->with(['availableAccount', 'reservedAccount', 'consumedAccount'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('advertising/billing', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'campaignFunding' => $campaigns->map(function (Campaign $campaign): array {
                $available = $this->budgetProjection->available($campaign);
                $reserved = $this->budgetProjection->reserved($campaign);
                $consumed = $this->budgetProjection->consumed($campaign);

                return [
                    'campaign_id' => $campaign->id,
                    'campaign_code' => $campaign->code,
                    'currency' => $campaign->currency,
                    'state' => $campaign->state->value,
                    'covered_total' => $available + $reserved + $consumed,
                    'billed_to_date' => $consumed,
                    'available' => $available,
                ];
            })->all(),
        ]);
    }
}
