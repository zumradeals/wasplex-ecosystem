<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\AudienceSegment;
use App\Modules\Advertising\Models\AudienceSegmentSizeThreshold;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Services\Exceptions\ForbiddenTargetingCriterionException;
use App\Modules\Advertising\Services\Exceptions\SegmentBelowMinimumThresholdException;
use RuntimeException;

/**
 * Frontière de correspondance d'audience (ADR-0010 §3) : ne restitue
 * jamais un segment sous le seuil minimal configuré tel quel (AMD-0009
 * §13), ne retourne jamais d'identité individuelle — {@see AudienceSegment}
 * ne stocke que des critères et une estimation agrégée, jamais un accès à
 * `identity`.
 */
class AudienceSegmentGuard
{
    /**
     * @param  array<string, mixed>  $criteria
     *
     * @throws ForbiddenTargetingCriterionException
     */
    public function createSegment(CampaignVersion $version, array $criteria, int $estimatedSize): AudienceSegment
    {
        AudienceCriteria::assertAllowed($criteria);

        $threshold = $this->activeThreshold();

        return AudienceSegment::create([
            'campaign_version_id' => $version->id,
            'criteria' => $criteria,
            'estimated_size' => $estimatedSize,
            'size_threshold_id' => $threshold->id,
            'below_threshold_at_creation' => $estimatedSize < $threshold->minimum_size,
        ]);
    }

    /**
     * La seule voie de lecture de la taille d'un segment destinée à
     * l'annonceur : sous le seuil, la correspondance est refusée plutôt
     * que retournée telle quelle (AMD-0009 §13). `estimated_size` reste
     * lisible en interne (audit, recalcul) mais n'est jamais la valeur
     * exposée par cette méthode dans ce cas.
     *
     * @throws SegmentBelowMinimumThresholdException
     */
    public function retrievableSize(AudienceSegment $segment): int
    {
        if ($segment->below_threshold_at_creation) {
            throw new SegmentBelowMinimumThresholdException(
                'segment sous le seuil minimal configuré : correspondance refusée (AMD-0009 §13)'
            );
        }

        return $segment->estimated_size;
    }

    private function activeThreshold(): AudienceSegmentSizeThreshold
    {
        $threshold = AudienceSegmentSizeThreshold::query()->where('state', 'active')->first();

        if ($threshold === null) {
            // Échec fermé : sans seuil publié, aucune estimation n'est
            // jamais exposée (ADR-0002 §7.3 « une opération financière dont
            // la règle ne peut être résolue de façon certaine échoue
            // fermée »).
            throw new RuntimeException('aucun seuil minimal de taille de segment actif (ADR-0002 §7.3)');
        }

        return $threshold;
    }
}
