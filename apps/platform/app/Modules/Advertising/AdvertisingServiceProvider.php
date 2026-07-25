<?php

namespace App\Modules\Advertising;

use App\Modules\Advertising\Http\Controllers\CampaignController;
use App\Modules\Advertising\Http\Controllers\CampaignFundingController;
use App\Modules\Advertising\Http\Controllers\CampaignReportController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionApprovalController;
use App\Modules\Advertising\Http\Controllers\CampaignVersionSubmissionController;
use App\Modules\Advertising\Http\Controllers\ModerationCaseDecisionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventAcceptanceController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventRejectionController;
use App\Modules\Advertising\Http\Controllers\QualifiedEventSubmissionController;
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
 * revue renforcée — ADR-0004 §12, ADR-0010 §5).
 *
 * Depuis P005-D, deux capacités couvrent le signalement et la modération :
 * `campaign.report` ({@see CampaignReportController}, largement disponible
 * à tout utilisateur authentifié — `03-signalements-sanctions-et-remuneration.md`
 * §1) et `campaign.moderate` ({@see ModerationCaseDecisionController},
 * décision + mesure conservatoire éventuelle, dont la suspension réelle
 * d'une Campaign). Aucune sanction formelle, aucun parcours de recours
 * complet n'existe encore (ADR-0010 §8).
 *
 * Depuis P005-E, `campaign.fund` ({@see CampaignFundingController}) est la
 * première capacité de tout l'écosystème à produire réellement une écriture
 * Ledger équilibrée (ADR-0010 §4 ligne 1 ; ADR-0003 §7-8) — réservée au
 * personnel finance Wasplex, jamais à l'annonceur lui-même.
 *
 * Depuis P005-F, trois capacités couvrent le cycle de vie complet d'un
 * QualifiedEvent (ADR-0010 §4 lignes 3-5) : `event.submit`
 * ({@see QualifiedEventSubmissionController}, réservation), `event.accept`
 * ({@see QualifiedEventAcceptanceController}, validation + répartition
 * 50/50 — première capacité à créer réellement un droit utilisateur
 * payable) et `event.reject` ({@see QualifiedEventRejectionController},
 * contre-écriture de la réservation). Toutes trois réservées au personnel
 * Wasplex anti-fraude/mesure d'attention : aucun moteur de prix versionné
 * (ADR-0002) n'existe encore pour permettre un vrai flux self-service par
 * le bénéficiaire (dette documentée, `event.submit`, migration
 * `2026_07_25_100009`).
 */
class AdvertisingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
