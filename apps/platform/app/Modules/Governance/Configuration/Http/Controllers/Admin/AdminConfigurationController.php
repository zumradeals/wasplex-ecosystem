<?php

namespace App\Modules\Governance\Configuration\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Configuration\Models\Approval;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Models\ValueVersion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurations (UX-0001 §8) : consultation seule du registre central de
 * configuration (ADR-0002) — chaque `Definition` avec l'historique complet
 * de ses `ValueVersion` (auteur, décisions d'approbation, activation).
 * Gouverné par `configuration.view`, une capacité de lecture exclusivement
 * (voir la migration de déclaration pour le raisonnement des dimensions) :
 * aucune action de proposition, d'approbation ni d'activation n'est
 * exposée par cet écran — TD-0006-A/G documentent pourquoi ce n'est pas
 * encore un lot sûr (aucune Simulation d'impact, aucune Definition réelle
 * proposée par un module métier à ce jour).
 */
class AdminConfigurationController extends Controller
{
    use ResolvesStaffVisibility;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'definitions' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/configurations', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $canView = $this->hasActiveStaffGrant($resolved['link'], 'configuration.view');

        if (! $canView) {
            return Inertia::render('admin/configurations', $deniedProps('no_active_grant'));
        }

        $definitions = Definition::query()
            ->with([
                'valueVersions' => fn ($query) => $query->orderByDesc('version'),
                'valueVersions.author.user',
                'valueVersions.approvals' => fn ($query) => $query->orderBy('decided_at'),
                'valueVersions.approvals.approver.user',
                'valueVersions.activation.activatedBy.user',
            ])
            ->orderBy('domain')
            ->orderBy('stable_key')
            ->orderByDesc('version')
            ->get();

        return Inertia::render('admin/configurations', [
            'access' => ['allowed' => true, 'reason' => null],
            'definitions' => $definitions->map(fn (Definition $definition): array => [
                'definition_id' => $definition->id,
                'stable_key' => $definition->stable_key,
                'version' => $definition->version,
                'domain' => $definition->domain,
                'level' => $definition->level->value,
                'value_type' => $definition->value_type->value,
                'unit' => $definition->unit,
                'description' => $definition->description,
                'state' => $definition->state->value,
                'value_versions' => $definition->valueVersions->map(fn (ValueVersion $version): array => [
                    'value_version_id' => $version->id,
                    'version' => $version->version,
                    'value' => $version->value,
                    'justification' => $version->justification,
                    'state' => $version->state->value,
                    'author_name' => $version->author?->user?->name,
                    'created_at' => $version->created_at->toIso8601String(),
                    'approvals' => $version->approvals->map(fn (Approval $approval): array => [
                        'approver_name' => $approval->approver?->user?->name,
                        'decision' => $approval->decision->value,
                        'motif' => $approval->motif,
                        'decided_at' => $approval->decided_at->toIso8601String(),
                    ])->values()->all(),
                    'activation' => $version->activation === null ? null : [
                        'activated_by_name' => $version->activation->activatedBy?->user?->name,
                        'activated_at' => $version->activation->activated_at->toIso8601String(),
                        'correlation_id' => $version->activation->correlation_id,
                    ],
                ])->values()->all(),
            ])->values()->all(),
        ]);
    }
}
