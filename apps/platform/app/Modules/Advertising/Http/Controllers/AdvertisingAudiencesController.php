<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Concerns\ResolvesAdvertiserWorkspace;
use App\Modules\Advertising\Models\AudienceSegment;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use App\Modules\Advertising\Services\Exceptions\SegmentBelowMinimumThresholdException;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audiences (UX-0001 §8) : critères et taille estimée de chaque segment
 * ciblé par une `CampaignVersion` du dossier annonceur.
 *
 * La taille n'est jamais lue directement sur le modèle : seule
 * {@see AudienceSegmentGuard::retrievableSize()} décide si elle est
 * communicable, et refuse fermé sous le seuil minimal configuré
 * (AMD-0009 §13) — ce contrôleur ne fait qu'exposer ce refus comme un état
 * d'écran (« non communiqué »), jamais contourner la garde.
 */
class AdvertisingAudiencesController extends Controller
{
    use ResolvesAdvertiserWorkspace;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AudienceSegmentGuard $audienceSegmentGuard,
    ) {}

    public function index(Request $request): Response
    {
        $workspace = $this->resolveAdvertiserWorkspace($request, 'advertising/audiences', [
            'audiences' => [],
        ]);

        if ($workspace instanceof Response) {
            return $workspace;
        }

        $profile = $workspace['profile'];

        $campaignIds = Campaign::query()
            ->where('advertiser_profile_id', $profile->id)
            ->pluck('id');

        $segments = AudienceSegment::query()
            ->whereHas('campaignVersion', fn ($query) => $query->whereIn('campaign_id', $campaignIds))
            ->with(['campaignVersion.campaign'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('advertising/audiences', [
            'access' => ['allowed' => true, 'reason' => null],
            'advertiserProfile' => $this->advertiserProfilePayload($profile),
            'audiences' => $segments->map(function (AudienceSegment $segment): array {
                $size = null;

                try {
                    $size = $this->audienceSegmentGuard->retrievableSize($segment);
                } catch (SegmentBelowMinimumThresholdException) {
                    $size = null;
                }

                return [
                    'campaign_id' => $segment->campaignVersion->campaign_id,
                    'campaign_code' => $segment->campaignVersion->campaign->code,
                    'campaign_version_id' => $segment->campaign_version_id,
                    'criteria' => $segment->criteria,
                    'estimated_size' => $size,
                    'below_threshold' => $segment->below_threshold_at_creation,
                ];
            })->all(),
        ]);
    }
}
