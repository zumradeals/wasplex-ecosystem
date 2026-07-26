<?php

namespace App\Modules\Advertising\Projections;

use App\Modules\Advertising\Models\CampaignVersionFavorite;
use App\Modules\Advertising\Models\CampaignVersionLike;
use App\Modules\Advertising\Models\CampaignVersionShare;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Collection;

/**
 * Compteurs réels de signaux sociaux (Lot 3 Phase A, décision de Koné
 * 2026-07-26) — toujours recalculés par agrégation sur les tables
 * `advertising.campaign_version_{likes,favorites,shares}`, jamais un
 * compteur stocké et incrémenté (même discipline que
 * `PersonBalanceProjection`/`CampaignBudgetProjection` : aucune valeur
 * mockée, jamais de solde ou de compte modifiable directement).
 */
class SocialEngagementProjection
{
    /**
     * Compteurs agrégés pour un lot de `CampaignVersion`, en trois
     * requêtes au total quel que soit le nombre de versions (jamais de
     * N+1 sur le Feed).
     *
     * @param  array<int, string>  $campaignVersionIds
     * @return array<string, array{likes: int, favorites: int, shares: int}>
     */
    public function countsForMany(array $campaignVersionIds): array
    {
        if ($campaignVersionIds === []) {
            return [];
        }

        $likes = CampaignVersionLike::query()
            ->whereIn('campaign_version_id', $campaignVersionIds)
            ->selectRaw('campaign_version_id, count(*) as total')
            ->groupBy('campaign_version_id')
            ->pluck('total', 'campaign_version_id');

        $favorites = CampaignVersionFavorite::query()
            ->whereIn('campaign_version_id', $campaignVersionIds)
            ->selectRaw('campaign_version_id, count(*) as total')
            ->groupBy('campaign_version_id')
            ->pluck('total', 'campaign_version_id');

        $shares = CampaignVersionShare::query()
            ->whereIn('campaign_version_id', $campaignVersionIds)
            ->selectRaw('campaign_version_id, count(*) as total')
            ->groupBy('campaign_version_id')
            ->pluck('total', 'campaign_version_id');

        $counts = [];

        foreach ($campaignVersionIds as $id) {
            $counts[$id] = [
                'likes' => (int) ($likes[$id] ?? 0),
                'favorites' => (int) ($favorites[$id] ?? 0),
                'shares' => (int) ($shares[$id] ?? 0),
            ];
        }

        return $counts;
    }

    /**
     * @return array{likes: int, favorites: int, shares: int}
     */
    public function counts(string $campaignVersionId): array
    {
        return $this->countsForMany([$campaignVersionId])[$campaignVersionId]
            ?? ['likes' => 0, 'favorites' => 0, 'shares' => 0];
    }

    /**
     * État du sujet (a-t-il aimé / mis en favori ?) pour un lot de
     * `CampaignVersion`, en deux requêtes.
     *
     * @param  array<int, string>  $campaignVersionIds
     * @return array<string, array{liked: bool, favorited: bool}>
     */
    public function viewerStateForMany(array $campaignVersionIds, PersonAccountLink $subject): array
    {
        if ($campaignVersionIds === []) {
            return [];
        }

        /** @var Collection<int, string> $likedIds */
        $likedIds = CampaignVersionLike::query()
            ->whereIn('campaign_version_id', $campaignVersionIds)
            ->where('person_account_link_id', $subject->id)
            ->pluck('campaign_version_id');

        /** @var Collection<int, string> $favoritedIds */
        $favoritedIds = CampaignVersionFavorite::query()
            ->whereIn('campaign_version_id', $campaignVersionIds)
            ->where('person_account_link_id', $subject->id)
            ->pluck('campaign_version_id');

        $state = [];

        foreach ($campaignVersionIds as $id) {
            $state[$id] = [
                'liked' => $likedIds->contains($id),
                'favorited' => $favoritedIds->contains($id),
            ];
        }

        return $state;
    }
}
