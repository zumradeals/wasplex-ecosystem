<?php

namespace App\Modules\Governance\Authorization\Projections;

use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Models\Grant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consultation seule du registre des `Grant` (ADR-0004 §5, §22), pour la
 * destination admin « Accès » (UX-0001 §8, `access.view`). Aucune méthode
 * de mutation ici : proposer, activer, suspendre ou révoquer un grant
 * reste exclusivement porté par `GrantManager` — cette classe ne fait que
 * lire ce qu'il a déjà écrit, jamais une deuxième source de vérité.
 */
class GrantDirectoryProjection
{
    /**
     * @return Collection<int, Grant>
     */
    public function list(?GrantState $state = null): Collection
    {
        return Grant::query()
            ->with([
                'personAccountLink.user',
                'capabilityDefinition',
                'author.user',
                'approver.user',
            ])
            ->when($state !== null, fn ($query) => $query->where('state', $state))
            ->orderByDesc('created_at')
            ->get();
    }
}
