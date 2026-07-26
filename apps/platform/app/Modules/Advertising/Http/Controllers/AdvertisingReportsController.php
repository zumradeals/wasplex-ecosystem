<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rapports (UX-0001 §8 « résultats agrégés ») : décompte des
 * `QualifiedEvent` par campagne et par statut de facturation
 * (`en attente` / `créditée` / `refusée`), avec le montant appliqué —
 * jamais une portée, un taux d'engagement ou une prévision inventés
 * (CLAUDE.md §2). Une publicité « en attente » n'est jamais présentée
 * comme un résultat acquis (UX-0001 §10).
 */
class AdvertisingReportsController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/reports', [
            'campaignReports' => [],
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $campaigns = Campaign::query()
            ->where('advertiser_profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->get();

        // `toBase()` : requête d'agrégation pure, jamais destinée à
        // hydrater des modèles `QualifiedEvent` — `event_count`/
        // `amount_total` ne sont pas des colonnes du modèle.
        $rows = QualifiedEvent::query()
            ->whereIn('campaign_id', $campaigns->pluck('id'))
            ->selectRaw('campaign_id, billing_status, count(*) as event_count, sum(applied_price_amount) as amount_total')
            ->groupBy('campaign_id', 'billing_status')
            ->toBase()
            ->get()
            ->groupBy('campaign_id');

        return Inertia::render('advertising/reports', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'campaignReports' => $campaigns->map(function (Campaign $campaign) use ($rows): array {
                $byStatus = ($rows->get($campaign->id) ?? collect())->keyBy(fn ($row): string => (string) $row->billing_status);
                $zero = (object) ['event_count' => 0, 'amount_total' => 0];

                $statusRow = fn (string $status): array => [
                    'event_count' => (int) $byStatus->get($status, $zero)->event_count,
                    'amount_total' => (int) $byStatus->get($status, $zero)->amount_total,
                ];

                return [
                    'campaign_id' => $campaign->id,
                    'campaign_code' => $campaign->code,
                    'currency' => $campaign->currency,
                    'pending' => $statusRow(BillingStatus::Pending->value),
                    'accepted' => $statusRow(BillingStatus::Accepted->value),
                    'rejected' => $statusRow(BillingStatus::Rejected->value),
                ];
            })->all(),
        ]);
    }
}
