<?php

namespace App\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\PublicationStatus;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\Publication;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use App\Modules\Alerts\Support\RecordsCaseEvents;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;

/**
 * Revue et publication d'un dossier `community` (ecosystem/alertes/03 §2).
 * Aucune catégorie sensible (`CaseCategory::requiresReinforcedReview()`)
 * n'est publiée sans passage explicite par ce service — jamais
 * automatiquement.
 */
class CaseModerationService
{
    use RecordsCaseEvents;

    public function startReview(AlertCase $case, PersonAccountLink $reviewer, string $correlationId): AlertCase
    {
        $this->assertTransitionAllowed($case, CommunityCaseState::UnderReview);

        return $this->transitionCase(
            $case,
            CommunityCaseState::UnderReview->value,
            'case_review_started',
            $correlationId,
            actor: $reviewer,
        );
    }

    /**
     * Publie une projection minimisée, distincte du dossier source
     * (ecosystem/alertes/03 §2.1). `$allowedFields` ne peut jamais dépasser
     * le maximum défini par la politique de catégorie — ce service ne
     * résout pas encore cette politique (registre Configuration, TD),
     * l'appelant doit fournir un ensemble déjà réduit et validé.
     *
     * @param  array<string, mixed>  $allowedFields
     */
    public function publish(
        AlertCase $case,
        PersonAccountLink $reviewer,
        string $title,
        string $summary,
        ?string $approximateZone,
        array $allowedFields,
        string $correlationId,
    ): AlertCase {
        $this->assertTransitionAllowed($case, CommunityCaseState::Published);

        return DB::transaction(function () use ($case, $reviewer, $title, $summary, $approximateZone, $allowedFields, $correlationId): AlertCase {
            $nextVersion = 1 + (int) Publication::query()->where('case_id', $case->id)->max('version');

            Publication::create([
                'case_id' => $case->id,
                'version' => $nextVersion,
                'title' => $title,
                'summary' => $summary,
                'approximate_zone' => $approximateZone,
                'allowed_fields' => $allowedFields,
                'status' => PublicationStatus::Published,
                'validated_by_person_account_link_id' => $reviewer->id,
                'published_at' => now(),
            ]);

            return $this->transitionCase(
                $case,
                CommunityCaseState::Published->value,
                'case_published',
                $correlationId,
                actor: $reviewer,
                metadata: ['publication_version' => $nextVersion],
            );
        });
    }

    public function restrict(AlertCase $case, PersonAccountLink $reviewer, string $reason, string $correlationId): AlertCase
    {
        $this->assertTransitionAllowed($case, CommunityCaseState::Restricted);

        return $this->transitionCase(
            $case,
            CommunityCaseState::Restricted->value,
            'case_restricted',
            $correlationId,
            actor: $reviewer,
            metadata: ['reason' => $reason],
        );
    }

    public function reject(AlertCase $case, PersonAccountLink $reviewer, string $reason, string $correlationId): AlertCase
    {
        $this->assertTransitionAllowed($case, CommunityCaseState::Rejected);

        return $this->transitionCase(
            $case,
            CommunityCaseState::Rejected->value,
            'case_rejected',
            $correlationId,
            actor: $reviewer,
            metadata: ['reason' => $reason],
            extraAttributes: ['closure_reason' => $reason, 'closed_at' => now()],
        );
    }

    /**
     * Retire la diffusion sans détruire le dossier source ni ses preuves
     * (AMD-0007 §15).
     */
    public function withdraw(AlertCase $case, ?PersonAccountLink $actor, string $reason, string $correlationId): AlertCase
    {
        $this->assertTransitionAllowed($case, CommunityCaseState::Withdrawn);

        return DB::transaction(function () use ($case, $actor, $reason, $correlationId): AlertCase {
            Publication::query()
                ->where('case_id', $case->id)
                ->where('status', PublicationStatus::Published)
                ->update(['status' => PublicationStatus::Withdrawn, 'withdrawn_at' => now(), 'withdrawal_reason' => $reason]);

            return $this->transitionCase(
                $case,
                CommunityCaseState::Withdrawn->value,
                'case_withdrawn',
                $correlationId,
                actor: $actor,
                metadata: ['reason' => $reason],
                extraAttributes: ['closure_reason' => $reason, 'closed_at' => now()],
            );
        });
    }

    private function assertTransitionAllowed(AlertCase $case, CommunityCaseState $to): void
    {
        $current = $case->communityState();

        if ($current === null || ! in_array($to, $current->allowedNextStates(), true)) {
            throw new InvalidCaseTransitionException("transition {$to->value} refusée depuis l'état {$case->state}");
        }
    }
}
