<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
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
 *
 * Expose aussi (chantier « espace annonceur cohérent avec le modèle
 * économique », véto du dirigeant) le prix unitaire réellement épinglé sur
 * la dernière version de chaque campagne et le nombre d'événements que le
 * budget disponible peut encore financer — un devis réel, jamais estimé
 * (docs/01-modele-economique-publicitaire.md §6 « nombre d'événements
 * visés » / §4 « le nombre de vues que son budget peut acheter »).
 *
 * La part Wasplex par événement n'est volontairement pas incluse dans ce
 * payload (instruction explicite du fondateur) : jamais communiquée à
 * l'annonceur.
 */
class AdvertisingBudgetController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly CampaignBudgetProjection $budgetProjection,
        private readonly QualifiedEventPricingResolver $pricingResolver,
        private readonly CampaignBudgetService $campaignBudgetService,
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
            ->with(['availableAccount', 'reservedAccount', 'consumedAccount', 'versions' => function ($query): void {
                $query->latest('created_at')->limit(1);
            }])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('advertising/budget', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'campaignBudgets' => $campaigns->map(function (Campaign $campaign): array {
                $available = $this->budgetProjection->available($campaign);
                $unitPrice = $this->unitPriceFor($campaign);

                return [
                    'campaign_id' => $campaign->id,
                    'campaign_code' => $campaign->code,
                    'currency' => $campaign->currency,
                    'state' => $campaign->state->value,
                    'available' => $available,
                    'reserved' => $this->budgetProjection->reserved($campaign),
                    'consumed' => $this->budgetProjection->consumed($campaign),
                    'unit_price' => $unitPrice,
                    'user_share_per_event' => $unitPrice !== null
                        ? $this->campaignBudgetService->userShareOfAmount($unitPrice)
                        : null,
                    'events_affordable' => $unitPrice !== null && $unitPrice > 0
                        ? intdiv($available, $unitPrice)
                        : null,
                ];
            })->all(),
        ]);
    }

    private function unitPriceFor(Campaign $campaign): ?int
    {
        $version = $campaign->versions->first();

        if ($version === null) {
            return null;
        }

        try {
            return $this->pricingResolver->resolveBasePrice($version);
        } catch (PricingConfigurationNotResolvableException) {
            return null;
        }
    }
}
