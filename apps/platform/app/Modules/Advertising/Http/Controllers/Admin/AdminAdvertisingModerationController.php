<?php

namespace App\Modules\Advertising\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\ModerationCase;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Publicité et modération (UX-0001 §8) : vue d'ensemble des campagnes et
 * de l'historique des dossiers de modération — au-delà de la seule file
 * « à traiter » de {@see ModerationOverviewController}, qui ne montre que
 * les éléments en attente d'une décision. Ici, le dossier déjà résolu
 * reste visible (`03-signalements-sanctions-et-remuneration.md` §1-2 :
 * traçabilité des décisions).
 *
 * Gouverné par `campaign.approve` ou `campaign.moderate` : les deux
 * capacités déjà responsables du cycle de vie d'une campagne diffusée.
 */
class AdminAdvertisingModerationController extends Controller
{
    use ResolvesStaffVisibility;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'campaigns' => [],
            'moderationCases' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/advertising-moderation', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $link = $resolved['link'];
        $canView = $this->hasActiveStaffGrant($link, 'campaign.approve')
            || $this->hasActiveStaffGrant($link, 'campaign.moderate');

        if (! $canView) {
            return Inertia::render('admin/advertising-moderation', $deniedProps('no_active_grant'));
        }

        $campaigns = Campaign::query()
            ->with(['advertiserProfile', 'versions' => function ($query): void {
                $query->orderByDesc('version');
            }])
            ->orderByDesc('created_at')
            ->get();

        $moderationCases = ModerationCase::query()
            ->with(['campaign.advertiserProfile'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('admin/advertising-moderation', [
            'access' => ['allowed' => true, 'reason' => null],
            'campaigns' => $campaigns->map(fn (Campaign $campaign): array => [
                'campaign_id' => $campaign->id,
                'code' => $campaign->code,
                'state' => $campaign->state->value,
                'advertiser_legal_name' => $campaign->advertiserProfile->legal_name,
                'latest_version_state' => $campaign->versions->first()?->state->value,
                'headline' => $campaign->versions->first()?->creations['headline'] ?? null,
            ])->all(),
            'moderationCases' => $moderationCases->map(fn (ModerationCase $case): array => [
                'moderation_case_id' => $case->id,
                'campaign_id' => $case->campaign->id,
                'campaign_code' => $case->campaign->code,
                'advertiser_legal_name' => $case->campaign->advertiserProfile->legal_name,
                'reason' => $case->reason,
                'severity' => $case->severity,
                'status' => $case->status->value,
                'precautionary_measure' => $case->precautionary_measure->value,
                'decision' => $case->decision?->value,
                'observed_destination' => $case->observed_destination,
                'opened_at' => $case->created_at->toIso8601String(),
            ])->all(),
        ]);
    }
}
