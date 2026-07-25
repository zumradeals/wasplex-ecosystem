<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\ModerationCaseStatus;
use App\Modules\Advertising\Enums\ModerationDecision;
use App\Modules\Advertising\Enums\PrecautionaryMeasure;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\ModerationCase;

/**
 * Signalement et mesures conservatoires (ADR-0010 §3, §4 dernière ligne ;
 * `03-signalements-sanctions-et-remuneration.md` §1-2). Aucune écriture
 * Ledger directe : la seule conséquence comptable possible est le blocage
 * applicatif de nouvelles réservations, jamais un mouvement de budget déjà
 * réservé ou consommé (ADR-0010 §4).
 */
class ModerationService
{
    public function openCase(
        Campaign $campaign,
        string $reason,
        string $severity,
        ?CampaignVersion $version = null,
        ?string $observedDestination = null,
    ): ModerationCase {
        return ModerationCase::create([
            'campaign_id' => $campaign->id,
            'campaign_version_id' => $version?->id,
            'reason' => $reason,
            'observed_destination' => $observedDestination,
            'severity' => $severity,
        ]);
    }

    /**
     * Clôture un ModerationCase (P005-D, `03-...md` §1-2). Toujours résolu
     * dès qu'une décision est rendue — ce lot ne construit aucun état
     * intermédiaire type « en instruction » (hors périmètre P005-D) :
     * `campaign.moderate` applique une décision définitive, éventuellement
     * accompagnée d'une mesure conservatoire ({@see applyPrecautionaryMeasure()},
     * appelée séparément par l'appelant lorsqu'une mesure est transmise).
     */
    public function recordDecision(ModerationCase $case, ModerationDecision $decision): ModerationCase
    {
        $case->forceFill([
            'decision' => $decision,
            'status' => ModerationCaseStatus::Resolved,
        ])->save();

        return $case->fresh();
    }

    /**
     * `CampaignSuspended` bloque toute nouvelle réservation via
     * `CampaignBudgetService::submitQualifiedEvent()` (ADR-0010 §7) ; le
     * budget déjà réservé ou consommé n'est jamais touché ici.
     */
    public function applyPrecautionaryMeasure(ModerationCase $case, PrecautionaryMeasure $measure): ModerationCase
    {
        $case->forceFill(['precautionary_measure' => $measure])->save();

        if ($measure === PrecautionaryMeasure::CampaignSuspended) {
            $case->campaign->forceFill(['state' => CampaignState::Suspended])->save();
        }

        return $case->fresh();
    }
}
