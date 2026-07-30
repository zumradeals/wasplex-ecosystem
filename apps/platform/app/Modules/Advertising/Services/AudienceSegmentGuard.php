<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\AudienceSegment;
use App\Modules\Advertising\Models\AudienceSegmentSizeThreshold;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use App\Modules\Advertising\Services\Exceptions\ForbiddenTargetingCriterionException;
use App\Modules\Advertising\Services\Exceptions\SegmentBelowMinimumThresholdException;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Taille réelle correspondant à des critères, calculée depuis le
     * profil publicitaire consenti (Lot 3, véto du dirigeant) — jamais une
     * déclaration de l'annonceur. Une clé de critère non reconnue ici
     * (`AudienceCriteria::assertAllowed()` n'impose qu'une liste noire,
     * jamais une liste blanche fermée) est simplement ignorée, jamais un
     * échec : un consentement retiré efface déjà les valeurs du profil
     * (`AdvertisingProfileService::withdrawConsent()`), donc ces profils
     * sortent naturellement de tout comptage, sans filtre supplémentaire.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function computeSize(array $criteria): int
    {
        return $this->matchingProfilesQuery($criteria)->count();
    }

    /**
     * Seule voie de lecture d'une taille non encore persistée (aperçu
     * avant création de campagne) : même masquage sous le seuil que
     * {@see retrievableSize()} (AMD-0009 §13) — jamais un petit nombre
     * exact exposé, même à titre d'aperçu.
     *
     * @param  array<string, mixed>  $criteria
     * @return array{estimated_size: int|null, below_threshold: bool}
     */
    public function estimateForPreview(array $criteria): array
    {
        $size = $this->computeSize($criteria);
        $threshold = $this->activeThreshold();
        $belowThreshold = $size < $threshold->minimum_size;

        return [
            'estimated_size' => $belowThreshold ? null : $size,
            'below_threshold' => $belowThreshold,
        ];
    }

    /**
     * Requête entièrement interne au schéma `advertising` —
     * `person_advertising_profiles` est déjà possédée par ce module,
     * aucune jointure vers `identity` (CLAUDE.md §6). `city`/`neighborhood`
     * sont en texte libre (aucune taxonomie de référence) : correspondance
     * insensible à la casse. Une dimension absente des critères ne filtre
     * pas (portée la plus large par défaut, sémantique standard de
     * ciblage publicitaire) ; `interests` correspond dès qu'au moins un
     * des codes demandés est présent (« ou »), jamais tous à la fois.
     *
     * @param  array<string, mixed>  $criteria
     * @return Builder<PersonAdvertisingProfile>
     */
    private function matchingProfilesQuery(array $criteria): Builder
    {
        $query = PersonAdvertisingProfile::query();

        if (! empty($criteria['country'])) {
            $query->whereIn('country_code', (array) $criteria['country']);
        }

        if (! empty($criteria['city'])) {
            $cities = array_map(strtolower(...), (array) $criteria['city']);
            $query->whereRaw('lower(city) IN ('.implode(',', array_fill(0, count($cities), '?')).')', $cities);
        }

        if (! empty($criteria['neighborhood'])) {
            $neighborhoods = array_map(strtolower(...), (array) $criteria['neighborhood']);
            $query->whereRaw('lower(neighborhood) IN ('.implode(',', array_fill(0, count($neighborhoods), '?')).')', $neighborhoods);
        }

        if (! empty($criteria['age_bracket'])) {
            $query->whereIn('age_bracket', (array) $criteria['age_bracket']);
        }

        if (! empty($criteria['gender'])) {
            $query->whereIn('gender', (array) $criteria['gender']);
        }

        if (! empty($criteria['interests'])) {
            $query->where(function (Builder $q) use ($criteria): void {
                foreach ((array) $criteria['interests'] as $code) {
                    $q->orWhereJsonContains('interests', $code);
                }
            });
        }

        return $query;
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
