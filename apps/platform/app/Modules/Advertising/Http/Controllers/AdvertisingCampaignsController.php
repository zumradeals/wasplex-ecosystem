<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Campagnes (UX-0001 §8) : table de toutes les campagnes du dossier
 * annonceur — équivalent du gestionnaire de campagnes façon « Ads
 * Manager », mais uniquement avec des colonnes reconstruites depuis des
 * ressources réelles (budget projeté, compte de publicités qualifiées par
 * statut de facturation) — jamais une métrique de portée ou d'engagement
 * inventée (CLAUDE.md §2 « aucun rendement, gain ou disponibilité de
 * campagne ne doit être garanti »).
 */
class AdvertisingCampaignsController extends Controller
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
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/campaigns', [
            'campaigns' => [],
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

        // `toBase()` : requête d'agrégation pure, jamais destinée à
        // hydrater des modèles `QualifiedEvent` — `event_count` n'est pas
        // une colonne du modèle.
        $eventCounts = QualifiedEvent::query()
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->selectRaw('campaign_id, billing_status, count(*) as event_count')
            ->groupBy('campaign_id', 'billing_status')
            ->toBase()
            ->get()
            ->groupBy('campaign_id');

        return Inertia::render('advertising/campaigns', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'campaigns' => $campaigns->map(function (Campaign $campaign) use ($eventCounts): array {
                $counts = ($eventCounts->get($campaign->id) ?? collect())
                    ->mapWithKeys(fn ($row) => [$row->billing_status => (int) $row->event_count]);

                return [
                    'id' => $campaign->id,
                    'code' => $campaign->code,
                    'currency' => $campaign->currency,
                    'state' => $campaign->state->value,
                    'latest_version_id' => $campaign->versions->first()?->id,
                    'latest_version_state' => $campaign->versions->first()?->state->value,
                    'headline' => $campaign->versions->first()?->creations['headline'] ?? null,
                    'created_at' => $campaign->created_at->toIso8601String(),
                    'budget' => [
                        'available' => $this->budgetProjection->available($campaign),
                        'reserved' => $this->budgetProjection->reserved($campaign),
                        'consumed' => $this->budgetProjection->consumed($campaign),
                    ],
                    'events' => [
                        'pending' => $counts->get(BillingStatus::Pending->value, 0),
                        'accepted' => $counts->get(BillingStatus::Accepted->value, 0),
                        'rejected' => $counts->get(BillingStatus::Rejected->value, 0),
                    ],
                ];
            })->all(),
        ]);
    }
}
