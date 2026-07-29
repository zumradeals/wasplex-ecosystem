<?php

namespace App\Modules\Alerts\Support;

use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\CaseEvent;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;

/**
 * Écrit une transition d'état et son événement append-only dans la même
 * transaction implicite que l'appelant (AMD-0007 §5 : « chaque statut
 * affiché correspond littéralement à une preuve réelle »). Le déclencheur
 * PostgreSQL `alerts.enforce_case_state_machine` reste l'autorité finale :
 * une transition refusée par la base fait échouer l'appel entier, jamais
 * une moitié d'écriture silencieuse.
 */
trait RecordsCaseEvents
{
    /**
     * `$extraAttributes` est fusionné dans le même `forceFill()`/`save()`
     * que le changement d'état : le déclencheur PostgreSQL
     * `alerts.enforce_case_state_machine` rejette toute UPDATE de
     * `alerts.cases` qui ne change pas `state` (« transition vers le même
     * état »), même une mise à jour d'une autre colonne seule — deux
     * `save()` séparés échoueraient donc sur le second.
     *
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $extraAttributes
     */
    private function transitionCase(
        AlertCase $case,
        string $toState,
        string $eventType,
        string $correlationId,
        ?PersonAccountLink $actor = null,
        ?Organization $actorOrganization = null,
        ?string $channel = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
        array $extraAttributes = [],
    ): AlertCase {
        return DB::transaction(function () use ($case, $toState, $eventType, $correlationId, $actor, $actorOrganization, $channel, $idempotencyKey, $metadata, $extraAttributes): AlertCase {
            $fromState = $case->state;

            $case->forceFill([...$extraAttributes, 'state' => $toState])->save();

            CaseEvent::create([
                'case_id' => $case->id,
                'event_type' => $eventType,
                'from_state' => $fromState,
                'to_state' => $toState,
                'actor_person_account_link_id' => $actor?->id,
                'actor_organization_id' => $actorOrganization?->id,
                'channel' => $channel,
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
            ]);

            return $case->fresh();
        });
    }

    /**
     * Événement sans transition d'état (ex. création du dossier lui-même,
     * déjà à son état initial).
     *
     * @param  array<string, mixed>  $metadata
     */
    private function recordCaseEvent(
        AlertCase $case,
        string $eventType,
        string $correlationId,
        ?PersonAccountLink $actor = null,
        ?Organization $actorOrganization = null,
        ?string $channel = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): void {
        CaseEvent::create([
            'case_id' => $case->id,
            'event_type' => $eventType,
            'from_state' => null,
            'to_state' => $case->state,
            'actor_person_account_link_id' => $actor?->id,
            'actor_organization_id' => $actorOrganization?->id,
            'channel' => $channel,
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $metadata,
        ]);
    }
}
