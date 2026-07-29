<?php

namespace App\Modules\Alerts\Projections;

use App\Modules\Alerts\Enums\PublicationStatus;
use App\Modules\Alerts\Models\Publication;
use Illuminate\Database\Eloquent\Collection;

/**
 * Liste publique des alertes communautaires diffusées (ecosystem/alertes/03
 * §2.1) — lit exclusivement `alerts.publications`, jamais `alerts.cases`
 * (le dossier source reste confidentiel).
 */
class PublicAlertFeedProjection
{
    /**
     * @return Collection<int, Publication>
     */
    public function published(?string $countryCode = null, int $limit = 50): Collection
    {
        return Publication::query()
            ->where('status', PublicationStatus::Published)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($countryCode !== null, fn ($query) => $query->whereHas('case', fn ($q) => $q->where('country_code', $countryCode)))
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
