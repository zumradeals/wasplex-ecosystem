<?php

namespace App\Modules\Governance\Authorization\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesStaffVisibility;
use App\Http\Controllers\Controller;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Projections\GrantDirectoryProjection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accès (UX-0001 §8) : consultation seule du registre des `Grant`
 * (ADR-0004 §5, §22) — qui détient quelle capacité, sa portée, sa fenêtre
 * de validité, son auteur/approbateur et, le cas échéant, son motif de
 * révocation. Gouverné par `access.view`, une capacité de lecture
 * exclusivement (voir la migration de déclaration pour le raisonnement des
 * dimensions) : aucune action de proposition, suspension, révocation ni
 * activation n'est exposée par cet écran — ces mutations restent
 * exclusivement portées par `GrantManager`, jamais dupliquées ici.
 */
class AdminAccessController extends Controller
{
    use ResolvesStaffVisibility;

    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly GrantDirectoryProjection $directory,
    ) {}

    public function index(Request $request): Response
    {
        $deniedProps = fn (string $reason): array => [
            'access' => ['allowed' => false, 'reason' => $reason],
            'grants' => [],
        ];

        $resolved = $this->resolveStaffSubject($request, 'admin/access', $deniedProps);

        if ($resolved instanceof Response) {
            return $resolved;
        }

        $canView = $this->hasActiveStaffGrant($resolved['link'], 'access.view');

        if (! $canView) {
            return Inertia::render('admin/access', $deniedProps('no_active_grant'));
        }

        $state = GrantState::tryFrom((string) $request->query('state'));

        return Inertia::render('admin/access', [
            'access' => ['allowed' => true, 'reason' => null],
            'grants' => $this->directory->list($state)->map(fn (Grant $grant): array => [
                'grant_id' => $grant->id,
                'holder_name' => $grant->personAccountLink?->user?->name,
                'capability_key' => $grant->capabilityDefinition->stable_key,
                'state' => $grant->state->value,
                'scope_type' => $grant->scope()->resourceType,
                'valid_from' => $grant->valid_from->toIso8601String(),
                'valid_until' => $grant->valid_until?->toIso8601String(),
                'author_name' => $grant->author?->user?->name,
                'approver_name' => $grant->approver?->user?->name,
                'revoked_at' => $grant->revoked_at?->toIso8601String(),
                'revocation_reason' => $grant->revocation_reason,
            ])->values()->all(),
        ]);
    }
}
