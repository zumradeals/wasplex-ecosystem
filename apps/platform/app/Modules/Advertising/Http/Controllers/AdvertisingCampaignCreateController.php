<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\AudienceSegmentSizeThreshold;
use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Advertising\Models\SectorClassification;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nouvelle campagne (UX-0001 §8, sous-écran de « Campagnes ») : formulaire
 * de création qui n'affiche que des contraintes réellement configurées
 * (secteurs actifs, formats autorisés par secteur, seuil minimal
 * d'audience actif) — jamais un tarif par durée ni une estimation de
 * portée/clics/WasPoints distribués : aucun de ces mécanismes n'existe
 * dans le registre de configuration aujourd'hui (`02-cycle-financier-campagne.md`
 * §6 « la formule exacte n'est pas codée en dur »). La soumission
 * elle-même reste {@see CampaignController::store()}, inchangée.
 */
class AdvertisingCampaignCreateController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function create(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/campaign-create', [
            'sectorClassifications' => [],
            'audienceSizeThreshold' => null,
            'interestTaxonomy' => [],
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
        ]);
    }
}
