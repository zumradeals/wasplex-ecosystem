<?php

namespace App\Modules\Alerts\Projections;

use App\Modules\Alerts\Models\InstitutionDispatch;
use App\Modules\Identity\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

/**
 * File de travail institutionnelle (ecosystem/institutions/01 §10) : ne
 * lit que les transmissions d'une organisation précise, jamais toutes les
 * organisations — « une même base PostgreSQL n'autorise jamais une
 * institution à explorer toutes les tables » (ecosystem/alertes/03 §1.1).
 */
class InstitutionalDispatchQueueProjection
{
    /**
     * @return Collection<int, InstitutionDispatch>
     */
    public function forOrganization(Organization $organization): Collection
    {
        return InstitutionDispatch::query()
            ->where('organization_id', $organization->id)
            ->with(['case'])
            ->orderByDesc('created_at')
            ->get();
    }
}
