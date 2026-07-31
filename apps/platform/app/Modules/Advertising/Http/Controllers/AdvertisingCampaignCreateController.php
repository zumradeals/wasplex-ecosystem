<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\AudienceSegmentSizeThreshold;
use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Advertising\Models\VideoAdDurationBounds;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Configuration\Services\ConfigurationResolver;
use App\Modules\Governance\Configuration\Services\Exceptions\NoActiveConfigurationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nouvelle campagne (UX-0001 §8, sous-écran de « Campagnes ») : formulaire
 * de création qui n'affiche que des contraintes réellement configurées
 * (secteurs actifs, formats autorisés par secteur, seuil minimal
 * d'audience actif) — jamais une estimation de portée/clics inventée.
 *
 * Expose désormais (chantier « espace annonceur cohérent avec le modèle
 * économique », véto du dirigeant) le prix unitaire actuellement résolvable
 * et sa répartition 50/50 réelle (AMD-0002), pour que l'annonceur voie un
 * devis fondé sur le moteur qui existe déjà plutôt qu'aucun chiffre du
 * tout — jamais présenté comme un catalogue tarifaire commercial : une
 * seule valeur, encore démonstrative (`AdvertisingDemoConfigurationSeeder`),
 * tant qu'aucun catalogue versionné par format/durée n'est décidé
 * (`docs/01-modele-economique-publicitaire.md` §5). La soumission
 * elle-même reste {@see CampaignController::store()}, inchangée.
 */
class AdvertisingCampaignCreateController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    private const PRICING_CONFIGURATION_KEY = 'advertising.qualified_event_base_price';

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly ConfigurationResolver $configurationResolver,
        private readonly CampaignBudgetService $campaignBudgetService,
    ) {}

    public function create(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/campaign-create', [
            'sectorClassifications' => [],
            'audienceSizeThreshold' => null,
            'interestTaxonomy' => [],
            'videoDurationBounds' => null,
            'indicativePricing' => null,
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $sectorClassifications = SectorClassification::query()
            ->where('state', 'active')
            ->orderBy('sector')
            ->get(['id', 'country_code', 'sector', 'allowed_formats', 'minimum_age', 'warnings']);

        $threshold = AudienceSegmentSizeThreshold::query()->where('state', 'active')->first();
        $videoDurationBounds = VideoAdDurationBounds::query()->where('state', 'active')->first();

        return Inertia::render('advertising/campaign-create', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'sectorClassifications' => $sectorClassifications->map(fn (SectorClassification $sector): array => [
                'id' => $sector->id,
                'label' => "{$sector->country_code} — {$sector->sector}",
                'country_code' => $sector->country_code,
                'allowed_formats' => $sector->allowed_formats,
                'minimum_age' => $sector->minimum_age,
                'warnings' => $sector->warnings,
            ])->all(),
            'audienceSizeThreshold' => $threshold?->minimum_size,
            'interestTaxonomy' => InterestTaxonomyEntry::query()
                ->where('state', 'active')
                ->orderBy('label')
                ->get(['code', 'label'])
                ->toArray(),
            'videoDurationBounds' => $videoDurationBounds === null ? null : [
                'min_seconds' => $videoDurationBounds->min_seconds,
                'max_seconds' => $videoDurationBounds->max_seconds,
            ],
            'indicativePricing' => $this->indicativePricing(),
        ]);
    }

    /**
     * @return array{unit_price: int, user_share: int, wasplex_share: int}|null
     */
    private function indicativePricing(): ?array
    {
        try {
            $unitPrice = (int) $this->configurationResolver->value(self::PRICING_CONFIGURATION_KEY);
        } catch (NoActiveConfigurationException) {
            return null;
        }

        return [
            'unit_price' => $unitPrice,
            'user_share' => $this->campaignBudgetService->userShareOfAmount($unitPrice),
            'wasplex_share' => $this->campaignBudgetService->wasplexShareOfAmount($unitPrice),
        ];
    }
}
