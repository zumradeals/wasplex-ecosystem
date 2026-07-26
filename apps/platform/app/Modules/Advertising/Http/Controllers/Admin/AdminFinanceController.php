<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance et rapprochement (UX-0001 §8) : état du budget de toutes les
 * campagnes et répartition des publicités qualifiées créditées
 * (part utilisateur / part Wasplex, ratio 50/50 constitutionnel exact,
 * AMD-0002) — reconstruit exclusivement depuis
 * {@see CampaignBudgetProjection} et les colonnes réelles de
 * `QualifiedEvent`, jamais un chiffre projeté ou estimé.
 *
 * Gouverné par `campaign.fund` : même capacité que l'action de
 * financement elle-même (voir {@see ModerationOverviewController}),
 * cohérent avec le principe que seul le personnel finance voit ces
 * données à l'échelle du système.
 */
class AdminFinanceController extends Controller
{
    use ResolvesStaffVisibility;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'budgetTotals' => [],
            'distributionTotals' => [],
            'campaigns' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/finance', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $canView = $this->hasActiveStaffGrant($resolved['link'], 'campaign.fund');

        if (! $canView) {
            return Inertia::render('admin/finance', $deniedProps('no_active_grant'));
        }

        $campaigns = Campaign::query()
            ->with(['advertiserProfile', 'availableAccount', 'reservedAccount', 'consumedAccount'])
            ->orderByDesc('created_at')
            ->get();

        $budgetTotalsByCurrency = [];

        foreach ($campaigns as $campaign) {
            $currency = $campaign->currency;
            $budgetTotalsByCurrency[$currency] ??= ['currency' => $currency, 'available' => 0, 'reserved' => 0, 'consumed' => 0];
            $budgetTotalsByCurrency[$currency]['available'] += $this->budgetProjection->available($campaign);
            $budgetTotalsByCurrency[$currency]['reserved'] += $this->budgetProjection->reserved($campaign);
            $budgetTotalsByCurrency[$currency]['consumed'] += $this->budgetProjection->consumed($campaign);
        }

        // `toBase()` : requête d'agrégation pure, jamais destinée à
        // hydrater des modèles `QualifiedEvent`.
        $acceptedTotals = QualifiedEvent::query()
            ->where('billing_status', BillingStatus::Accepted)
            ->select('applied_price_currency', DB::raw('sum(applied_price_amount) as amount_total'))
            ->groupBy('applied_price_currency')
            ->toBase()
            ->get()
            ->map(function ($row): array {
                $total = (int) $row->amount_total;
                // AMD-0002 : ratio 50/50 exact ; unité résiduelle à
                // l'utilisateur (décision du fondateur 2026-07-26,
                // ADR-0010) — même règle que
                // CampaignBudgetService::acceptQualifiedEvent(). Approximation
                // agrégée (répartition du total, pas somme des répartitions
                // par événement) : n'égale la somme réelle des parts
                // individuelles que si aucun événement accepté n'a un
                // montant impair isolé — suffisant pour un totalisateur de
                // tableau de bord, jamais pour une écriture Ledger.
                $userShare = intdiv($total + 1, 2);

                return [
                    'currency' => $row->applied_price_currency,
                    'accepted_total' => $total,
                    'user_share' => $userShare,
                    'wasplex_share' => $total - $userShare,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/finance', [
            'access' => ['allowed' => true, 'reason' => null],
            'budgetTotals' => array_values($budgetTotalsByCurrency),
            'distributionTotals' => $acceptedTotals,
            'campaigns' => $campaigns->map(fn (Campaign $campaign): array => [
                'campaign_id' => $campaign->id,
                'code' => $campaign->code,
                'currency' => $campaign->currency,
                'state' => $campaign->state->value,
                'advertiser_legal_name' => $campaign->advertiserProfile->legal_name,
                'available' => $this->budgetProjection->available($campaign),
                'reserved' => $this->budgetProjection->reserved($campaign),
                'consumed' => $this->budgetProjection->consumed($campaign),
            ])->all(),
        ]);
    }
}
