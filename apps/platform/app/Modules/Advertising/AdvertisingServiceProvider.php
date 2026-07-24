<?php

namespace App\Modules\Advertising;

use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
use Illuminate\Support\ServiceProvider;

/**
 * Frontière du module Publicité (ADR-0010) : déclare ses propres
 * migrations, sans exposer ses modèles internes en dehors de ses services
 * publics.
 *
 * Depuis P005-B, le module déclare sa première capacité réelle
 * (`campaign.create`, ADR-0004 §11) et expose sa première route sensible
 * (`POST /advertising/campaigns`, {@see CampaignController}) —
 * strictement limitée à la soumission d'une campagne en draft par son
 * auteur.
 *
 * Depuis P005-C, deux capacités supplémentaires couvrent le cycle de revue
 * d'une CampaignVersion : `campaign.submit_for_review`
 * ({@see CampaignVersionSubmissionController}, draft → in_review) et
 * `campaign.approve` ({@see CampaignVersionApprovalController},
 * in_review → approved, avec séparation des tâches pour les secteurs à
 * revue renforcée — ADR-0004 §12, ADR-0010 §5). Aucune capacité de
 * modération ni de diffusion n'existe encore (ADR-0010 §8).
 */
class AdvertisingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
