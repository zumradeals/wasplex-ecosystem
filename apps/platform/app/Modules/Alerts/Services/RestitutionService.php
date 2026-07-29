<?php

namespace App\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\RestitutionState;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\CorrespondenceReport;
use App\Modules\Alerts\Models\Restitution;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use App\Modules\Alerts\Support\RecordsCaseEvents;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Restitution sécurisée (ecosystem/institutions/01 §8) : le code est
 * généré une seule fois, communiqué hors de ce service (canal déjà
 * authentifié du déclarant), et seul son condensat est stocké. Remise et
 * réception restent deux confirmations distinctes, jamais une seule action.
 */
class RestitutionService
{
    use RecordsCaseEvents;

    /**
     * @return array{restitution: Restitution, code: string} Le code en clair n'est jamais persisté ; il n'existe que le temps de cet appel pour être transmis au déclarant.
     */
    public function issueCode(
        AlertCase $case,
        CorrespondenceReport $correspondenceReport,
        ?Organization $organization,
        int $expiresInMinutes,
        string $correlationId,
    ): array {
        $code = (string) random_int(100000, 999999);

        $restitution = DB::transaction(function () use ($case, $correspondenceReport, $organization, $expiresInMinutes, $code, $correlationId): Restitution {
            $restitution = Restitution::create([
                'case_id' => $case->id,
                'correspondence_report_id' => $correspondenceReport->id,
                'organization_id' => $organization?->id,
                'state' => RestitutionState::CodeIssued,
                'code_hash' => Hash::make($code),
                'code_expires_at' => now()->addMinutes($expiresInMinutes),
            ]);

            $this->assertCommunityTransition($case, CommunityCaseState::RestitutionScheduled);
            $this->transitionCase($case, CommunityCaseState::RestitutionScheduled->value, 'restitution_scheduled', $correlationId, metadata: ['restitution_id' => $restitution->id]);

            return $restitution;
        });

        return ['restitution' => $restitution, 'code' => $code];
    }

    public function confirmDelivery(Restitution $restitution, PersonAccountLink $deliveredBy, string $suppliedCode, string $correlationId): Restitution
    {
        $this->assertState($restitution, RestitutionState::CodeIssued);

        if ($restitution->code_expires_at !== null && now()->greaterThanOrEqualTo($restitution->code_expires_at)) {
            $restitution->forceFill(['state' => RestitutionState::Expired])->save();

            throw new InvalidCaseTransitionException("code de restitution {$restitution->id} expiré");
        }

        if ($restitution->code_hash === null || ! Hash::check($suppliedCode, $restitution->code_hash)) {
            throw new InvalidCaseTransitionException("code de restitution incorrect pour {$restitution->id}");
        }

        $restitution->forceFill([
            'state' => RestitutionState::Delivered,
            'delivered_at' => now(),
            'delivered_confirmed_by_person_account_link_id' => $deliveredBy->id,
        ])->save();

        $this->recordCaseEvent($restitution->case, 'restitution_delivered', $correlationId, actor: $deliveredBy, metadata: ['restitution_id' => $restitution->id]);

        return $restitution->fresh();
    }

    public function confirmReception(Restitution $restitution, PersonAccountLink $receivedBy, ?PersonAccountLink $witness, string $correlationId): Restitution
    {
        $this->assertState($restitution, RestitutionState::Delivered);

        $restitution->forceFill([
            'state' => RestitutionState::Received,
            'received_at' => now(),
            'received_confirmed_by_person_account_link_id' => $receivedBy->id,
            'witness_person_account_link_id' => $witness?->id,
        ])->save();

        $this->recordCaseEvent($restitution->case, 'restitution_received', $correlationId, actor: $receivedBy, metadata: ['restitution_id' => $restitution->id]);

        return $restitution->fresh();
    }

    public function complete(Restitution $restitution, ?PersonAccountLink $actor, string $correlationId): Restitution
    {
        $this->assertState($restitution, RestitutionState::Received);

        return DB::transaction(function () use ($restitution, $actor, $correlationId): Restitution {
            $restitution->forceFill(['state' => RestitutionState::Completed])->save();

            $case = $restitution->case;
            $this->assertCommunityTransition($case, CommunityCaseState::Resolved);
            $this->transitionCase($case, CommunityCaseState::Resolved->value, 'case_resolved', $correlationId, actor: $actor, metadata: ['restitution_id' => $restitution->id], extraAttributes: ['closed_at' => now()]);

            return $restitution->fresh();
        });
    }

    public function dispute(Restitution $restitution, string $reason, string $correlationId): Restitution
    {
        $restitution->forceFill(['state' => RestitutionState::Disputed, 'dispute_reason' => $reason])->save();

        $case = $restitution->case;
        $current = $case->communityState();

        if ($current !== null && in_array(CommunityCaseState::Disputed, $current->allowedNextStates(), true)) {
            $this->transitionCase($case, CommunityCaseState::Disputed->value, 'case_disputed', $correlationId, metadata: ['restitution_id' => $restitution->id, 'reason' => $reason]);
        }

        return $restitution->fresh();
    }

    private function assertState(Restitution $restitution, RestitutionState $expected): void
    {
        if ($restitution->state !== $expected) {
            throw new InvalidCaseTransitionException(
                "restitution {$restitution->id} attendue à l'état {$expected->value}, actuellement {$restitution->state->value}"
            );
        }
    }

    private function assertCommunityTransition(AlertCase $case, CommunityCaseState $to): void
    {
        $current = $case->communityState();

        if ($current === null || ! in_array($to, $current->allowedNextStates(), true)) {
            throw new InvalidCaseTransitionException("transition {$to->value} refusée depuis l'état {$case->state}");
        }
    }
}
