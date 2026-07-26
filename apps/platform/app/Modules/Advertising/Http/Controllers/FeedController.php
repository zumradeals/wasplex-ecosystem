<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feed (W4, L03 UX-0003) : point d'entrée quotidien de l'utilisateur —
 * remplace le placeholder générique de `/dashboard`. Débloque enfin
 * l'affichage réel des campagnes déjà construites par P005 A-F.
 *
 * Aucune capacité `Governance/Authorization` nouvelle : parcourir les
 * campagnes diffusées est un contenu public de diffusion, jamais une
 * ressource personnelle (à la différence de `wallet.view`/`campaign.view`,
 * qui protègent des données propres à une personne ou un dossier
 * annonceur). Seule la soumission de preuve (`event.self_submit`, déjà
 * gouvernée) exige une autorisation réelle.
 *
 * Volontairement minimal (noyau, TD-0006 lignée) : une seule campagne
 * approuvée à la fois par définition existante (ADR-0010 §5), aucune
 * correspondance d'audience réelle par personne (`AudienceSegment` reste
 * un agrégat, jamais un ciblage individuel — AMD-0009 §13) : ce noyau
 * montre toute campagne active, approuvée, financée et dont le prix est
 * résolvable, sans encore filtrer par pertinence d'audience ni limiter la
 * fréquence par utilisateur (ADR-0002 §3.3, quotas différés).
 */
class FeedController extends Controller
{
    public function __construct(
        private readonly CampaignBudgetProjection $budgetProjection,
        private readonly QualifiedEventPricingResolver $pricingResolver,
    ) {}

    public function index(Request $request): Response
    {
        $campaigns = Campaign::query()
            ->where('state', CampaignState::Active)
            ->with(['advertiserProfile', 'versions' => function ($query): void {
                $query->where('state', CampaignVersionState::Approved);
            }])
            ->get()
            ->filter(fn (Campaign $campaign): bool => $campaign->versions->isNotEmpty());

        $ads = $campaigns
            ->map(function (Campaign $campaign): ?array {
                $version = $campaign->versions->first();

                return $this->eligibleAd($campaign, $version);
            })
            ->filter()
            ->values()
            ->all();

        return Inertia::render('dashboard', ['ads' => $ads]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function eligibleAd(Campaign $campaign, CampaignVersion $version): ?array
    {
        $available = $this->budgetProjection->available($campaign);

        if ($available <= 0) {
            return null;
        }

        try {
            $reward = $this->pricingResolver->resolveBasePrice($version);
        } catch (PricingConfigurationNotResolvableException) {
            return null;
        }

        if ($reward > $available) {
            return null;
        }

        return [
            'campaign_version_id' => $version->id,
            // UX-0001 §9 : « Avant une publicité, l'utilisateur voit :
            // annonceur ; format ; durée ou condition ; gain potentiel… ».
            // Le nom légal est la seule identité annonceur constitutionnelle —
            // jamais un pseudonyme commercial servant de clé (CLAUDE.md §2).
            'advertiser' => $campaign->advertiserProfile->legal_name,
            'headline' => $version->creations['headline'] ?? $campaign->code,
            'format' => $version->expected_event['format'] ?? 'display',
            'condition' => $version->expected_event['condition'] ?? 'completion',
            'reward_amount' => $reward,
            'currency' => $campaign->currency,
        ];
    }
}
