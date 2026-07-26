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
 * Budget (UX-0001 §8) : combien chaque campagne peut encore engager, en
 * temps réel. Reconstruit exclusivement depuis
 * {@see CampaignBudgetProjection} (elle-même reconstruite depuis le
 * Ledger — ADR-0010 §3) : aucune colonne de solde mise en cache, aucune
 * projection de dépense future devinée
 * (`02-cycle-financier-campagne.md` §3).
 */
class AdvertisingBudgetController extends Controller
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
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/budget', [
            'campaignBudgets' => [],
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

        return Inertia::render('advertising/budget', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'campaignBudgets' => $campaigns->map(fn (Campaign $campaign): array => [
                'campaign_id' => $campaign->id,
                'campaign_code' => $campaign->code,
                'currency' => $campaign->currency,
                'state' => $campaign->state->value,
                'available' => $this->budgetProjection->available($campaign),
                'reserved' => $this->budgetProjection->reserved($campaign),
                'consumed' => $this->budgetProjection->consumed($campaign),
            ])->all(),
        ]);
    }
}
