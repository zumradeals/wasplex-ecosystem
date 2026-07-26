<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Wallet\Balance\Http\Controllers\WalletOverviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vue d'ensemble du portail annonceur (P007-W2, UX-0001 §8 « Vue
 * d'ensemble »). Panorama agrégé de tout ce que le dossier annonceur
 * possède — jamais le détail d'une campagne (voir
 * {@see AdvertisingCampaignsController}) : chaque nombre affiché ici est
 * une somme ou un comptage reconstruit depuis des ressources réellement
 * appartenant au représentant courant, jamais une estimation inventée
 * (CLAUDE.md §6 « ne jamais inventer un succès »).
 *
 * Même discipline que {@see WalletOverviewController} :
 * un refus d'autorisation ou l'absence de dossier restitue un état
 * d'écran, jamais une erreur JSON brute.
 */
class AdvertisingOverviewController extends Controller
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
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/overview', [
            'campaignCounts' => [],
            'budgetTotals' => [],
            'eventTotals' => [],
            'recentCampaigns' => [],
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $campaigns = Campaign::query()
            ->where('advertiser_profile_id', $profile->id)
            ->with(['availableAccount', 'reservedAccount', 'consumedAccount', 'versions' => function ($query): void {
                $query->orderByDesc('version');
            }])
            ->orderByDesc('created_at')
            ->get();

        // Sommé par devise : des campagnes en devises différentes ne
        // partagent jamais un même total (une somme brute mélangerait des
        // unités incomparables).
        $budgetTotalsByCurrency = [];

        foreach ($campaigns as $campaign) {
            $currency = $campaign->currency;
            $budgetTotalsByCurrency[$currency] ??= ['currency' => $currency, 'available' => 0, 'reserved' => 0, 'consumed' => 0];
            $budgetTotalsByCurrency[$currency]['available'] += $this->budgetProjection->available($campaign);
            $budgetTotalsByCurrency[$currency]['reserved'] += $this->budgetProjection->reserved($campaign);
            $budgetTotalsByCurrency[$currency]['consumed'] += $this->budgetProjection->consumed($campaign);
        }

        $campaignCounts = $campaigns->countBy(fn (Campaign $campaign): string => $campaign->state->value)->all();

        // `toBase()` : requête d'agrégation pure (comptages/sommes), jamais
        // destinée à hydrater des modèles `QualifiedEvent` — les alias SQL
        // (`event_count`, `amount_total`) ne sont pas des colonnes du
        // modèle (ADR-0010 §3, cette table n'a pas vocation à exposer un
        // total agrégé comme attribut).
        $eventTotals = QualifiedEvent::query()
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->select('billing_status', 'applied_price_currency', DB::raw('count(*) as event_count'), DB::raw('sum(applied_price_amount) as amount_total'))
            ->groupBy('billing_status', 'applied_price_currency')
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'billing_status' => $row->billing_status,
                'currency' => $row->applied_price_currency,
                'event_count' => (int) $row->event_count,
                'amount_total' => (int) $row->amount_total,
            ])
            ->all();

        return Inertia::render('advertising/overview', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'campaignCounts' => $campaignCounts,
            'budgetTotals' => array_values($budgetTotalsByCurrency),
            'eventTotals' => $eventTotals,
            'recentCampaigns' => $campaigns->take(5)->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'code' => $campaign->code,
                'currency' => $campaign->currency,
                'state' => $campaign->state->value,
                'latest_version_state' => $campaign->versions->first()?->state->value,
            ])->all(),
        ]);
    }
}
