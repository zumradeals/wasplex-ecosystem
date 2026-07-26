<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Créations (UX-0001 §8) : contenu publicitaire (titre, format, condition,
 * destination) de chaque `CampaignVersion` du dossier annonceur — jamais
 * un média réel (stockage objet/CDN différé à W5, même limite que
 * {@see FeedController}), et le
 * secteur affiché rappelle les exigences réellement configurées
 * (formats autorisés, âge minimal, avertissements) plutôt qu'un texte de
 * politique publicitaire inventé.
 */
class AdvertisingCreationsController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/creations', [
            'creations' => [],
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $campaignIds = Campaign::query()
            ->where('advertiser_profile_id', $profile->id)
            ->pluck('id');

        $versions = CampaignVersion::query()
            ->whereIn('campaign_id', $campaignIds)
            ->with(['campaign', 'sectorClassification'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('advertising/creations', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'creations' => $versions->map(fn (CampaignVersion $version): array => [
                'campaign_id' => $version->campaign_id,
                'campaign_code' => $version->campaign->code,
                'campaign_version_id' => $version->id,
                'version' => $version->version,
                'state' => $version->state->value,
                'headline' => $version->creations['headline'] ?? null,
                'format' => $version->expected_event['format'] ?? null,
                'condition' => $version->expected_event['condition'] ?? null,
                'destination_url' => $version->destination['url'] ?? null,
                'territory' => $version->territory,
                'sector' => [
                    'label' => "{$version->sectorClassification->country_code} — {$version->sectorClassification->sector}",
                    'allowed_formats' => $version->sectorClassification->allowed_formats,
                    'minimum_age' => $version->sectorClassification->minimum_age,
                    'warnings' => $version->sectorClassification->warnings,
                ],
            ])->all(),
        ]);
    }
}
