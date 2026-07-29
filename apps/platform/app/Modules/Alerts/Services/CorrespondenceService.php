<?php

namespace App\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\CorrespondenceReviewState;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\CorrespondenceReport;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use App\Modules\Alerts\Support\RecordsCaseEvents;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;

/**
 * Correspondances (ecosystem/alertes/02 §7) : le moteur produit un
 * candidat, jamais une décision finale. Les caractéristiques secrètes du
 * dossier source ne transitent jamais par ce service vers le déclarant —
 * seule sa réponse (`$verificationResponse`) est enregistrée.
 */
class CorrespondenceService
{
    use RecordsCaseEvents;

    /**
     * @param  array<string, mixed>  $verificationResponse
     */
    public function report(
        AlertCase $case,
        PersonAccountLink $reporter,
        string $nonPublicDescription,
        array $verificationResponse,
        string $correlationId,
    ): CorrespondenceReport {
        return DB::transaction(function () use ($case, $reporter, $nonPublicDescription, $verificationResponse, $correlationId): CorrespondenceReport {
            $report = CorrespondenceReport::create([
                'case_id' => $case->id,
                'reporter_person_account_link_id' => $reporter->id,
                'non_public_description' => $nonPublicDescription,
                'verification_response' => $verificationResponse,
                'review_state' => CorrespondenceReviewState::Pending,
            ]);

            $this->recordCaseEvent($case, 'correspondence_reported', $correlationId, actor: $reporter, metadata: ['correspondence_report_id' => $report->id]);

            return $report;
        });
    }

    /**
     * Validation humaine (AMD-0007 §9 : « une correspondance automatisée
     * reste une hypothèse et ne décide pas seule d'un dossier humain
     * sensible »). Fait passer le dossier `community` à `matched` s'il est
     * encore diffusé.
     */
    public function validate(CorrespondenceReport $report, PersonAccountLink $reviewer, string $correlationId): CorrespondenceReport
    {
        return DB::transaction(function () use ($report, $reviewer, $correlationId): CorrespondenceReport {
            $report->forceFill([
                'review_state' => CorrespondenceReviewState::Validated,
                'reviewed_by_person_account_link_id' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            $case = $report->case;
            $current = $case->communityState();

            if ($current === null || ! in_array(CommunityCaseState::Matched, $current->allowedNextStates(), true)) {
                throw new InvalidCaseTransitionException(
                    "le dossier {$case->id} n'est pas dans un état permettant une correspondance validée (état actuel : {$case->state})"
                );
            }

            $case->forceFill(['state' => CommunityCaseState::Matched->value])->save();
            $this->recordCaseEvent($case, 'case_matched', $correlationId, actor: $reviewer, metadata: ['correspondence_report_id' => $report->id]);

            return $report->fresh();
        });
    }

    public function reject(CorrespondenceReport $report, PersonAccountLink $reviewer, string $correlationId): CorrespondenceReport
    {
        $report->forceFill([
            'review_state' => CorrespondenceReviewState::Rejected,
            'reviewed_by_person_account_link_id' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->recordCaseEvent($report->case, 'correspondence_rejected', $correlationId, actor: $reviewer, metadata: ['correspondence_report_id' => $report->id]);

        return $report->fresh();
    }
}
