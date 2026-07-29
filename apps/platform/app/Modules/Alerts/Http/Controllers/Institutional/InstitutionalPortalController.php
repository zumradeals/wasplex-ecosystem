<?php

namespace App\Modules\Alerts\Http\Controllers\Institutional;

use App\Http\Controllers\Controller;
use App\Modules\Alerts\Http\Controllers\Institutional\Concerns\ResolvesInstitutionalMembership;
use App\Modules\Alerts\Models\InstitutionDispatch;
use App\Modules\Alerts\Projections\InstitutionalDispatchQueueProjection;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portail des institutions Wasplex (ecosystem/institutions/01 §10) —
 * jamais « portail agents » ni « espace agents ». Affiche organisation,
 * utilisateur connecté, capacités actives et la file de dossiers transmis
 * à cette organisation précise, jamais toutes les organisations.
 */
class InstitutionalPortalController extends Controller
{
    use ResolvesInstitutionalMembership;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly InstitutionalDispatchQueueProjection $queue,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'organization' => null,
            'capabilities' => [],
            'dispatches' => [],
        ];

        $resolved = $this->resolveInstitutionalSubject($request, 'institutions/alerts/index', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        ['link' => $link, 'organization' => $organization] = $resolved;

        $capabilityKeys = [
            'alert_case.acknowledge', 'alert_case.accept', 'alert_case.process',
            'alert_case.resolve', 'alert_case.transfer',
        ];

        $capabilities = array_values(array_filter(
            $capabilityKeys,
            fn (string $key): bool => $this->hasActiveInstitutionalGrant($link, $organization, $key),
        ));

        $dispatches = $this->queue->forOrganization($organization)
            ->map(fn (InstitutionDispatch $dispatch): array => [
                'dispatch_id' => $dispatch->id,
                'case_id' => $dispatch->case_id,
                'category' => $dispatch->category->value,
                'state' => $dispatch->state->value,
                'territory_code' => $dispatch->territory_code,
                'transmitted_at' => $dispatch->transmitted_at?->toIso8601String(),
                'received_at' => $dispatch->received_at?->toIso8601String(),
                'accepted_at' => $dispatch->accepted_at?->toIso8601String(),
                'created_at' => $dispatch->created_at->toIso8601String(),
                'source_description' => $dispatch->case->source_description,
                'case_nature' => $dispatch->case->nature->value,
            ])
            ->values()
            ->all();

        return Inertia::render('institutions/alerts/index', [
            'access' => ['allowed' => true, 'reason' => null],
            'organization' => [
                'organization_id' => $organization->id,
                'display_name' => $organization->display_name,
                'country_code' => $organization->country_code,
            ],
            'capabilities' => $capabilities,
            'dispatches' => $dispatches,
        ]);
    }
}
