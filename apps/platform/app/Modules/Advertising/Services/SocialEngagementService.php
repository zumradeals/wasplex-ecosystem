<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\CampaignVersionFavorite;
use App\Modules\Advertising\Models\CampaignVersionLike;
use App\Modules\Advertising\Models\CampaignVersionShare;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Database\QueryException;

/**
 * Signaux sociaux purs sur une publicité (Lot 3 Phase A, menu vertical du
 * Feed, décision de Koné 2026-07-26) : j'aime, favori, intention de
 * partage. AUCUN effet financier — cette classe n'importe ni n'appelle
 * jamais `LedgerPoster`, `CampaignBudgetService` ni aucun composant Wallet
 * (contrainte explicite de la décision). Ne modifie ni prix, ni quota, ni
 * décision d'acceptation d'un `QualifiedEvent`.
 *
 * Like et favori sont des bascules (toggle) : re-appeler retire ce qui
 * était présent, idempotent par construction via l'unicité de schéma
 * (`campaign_version_id`, `person_account_link_id`). Le partage est un
 * événement répétable, jamais une bascule.
 */
class SocialEngagementService
{
    /**
     * @return bool `true` si le like est désormais actif, `false` s'il
     *              vient d'être retiré.
     */
    public function toggleLike(CampaignVersion $version, PersonAccountLink $subject): bool
    {
        $existing = CampaignVersionLike::query()
            ->where('campaign_version_id', $version->id)
            ->where('person_account_link_id', $subject->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        try {
            CampaignVersionLike::create([
                'campaign_version_id' => $version->id,
                'person_account_link_id' => $subject->id,
            ]);

            return true;
        } catch (QueryException $exception) {
            // Course perdue entre deux bascules concurrentes de la même
            // personne (double-tap) : l'autre a déjà créé la ligne,
            // considérer le like comme actif plutôt que faire échouer la
            // requête sur une contrainte d'unicité.
            $stillMissing = CampaignVersionLike::query()
                ->where('campaign_version_id', $version->id)
                ->where('person_account_link_id', $subject->id)
                ->doesntExist();

            if ($stillMissing) {
                throw $exception;
            }

            return true;
        }
    }

    /**
     * @return bool `true` si le favori est désormais actif, `false` s'il
     *              vient d'être retiré.
     */
    public function toggleFavorite(CampaignVersion $version, PersonAccountLink $subject): bool
    {
        $existing = CampaignVersionFavorite::query()
            ->where('campaign_version_id', $version->id)
            ->where('person_account_link_id', $subject->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        try {
            CampaignVersionFavorite::create([
                'campaign_version_id' => $version->id,
                'person_account_link_id' => $subject->id,
            ]);

            return true;
        } catch (QueryException $exception) {
            $stillMissing = CampaignVersionFavorite::query()
                ->where('campaign_version_id', $version->id)
                ->where('person_account_link_id', $subject->id)
                ->doesntExist();

            if ($stillMissing) {
                throw $exception;
            }

            return true;
        }
    }

    /**
     * Enregistre une intention de partage — jamais une bascule, chaque
     * appel crée une nouvelle ligne (le partage effectif a lieu hors
     * plateforme, voir `advertising.campaign_version_shares`).
     */
    public function recordShare(CampaignVersion $version, PersonAccountLink $subject): CampaignVersionShare
    {
        return CampaignVersionShare::create([
            'campaign_version_id' => $version->id,
            'person_account_link_id' => $subject->id,
        ]);
    }
}
