<?php

namespace App\Modules\Alerts\Services;

use App\Modules\Alerts\Enums\DispatchState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Alerts\Models\InstitutionDispatch;
use App\Modules\Alerts\Projections\InstitutionRoutingProjection;
use App\Modules\Alerts\Services\Exceptions\InvalidCaseTransitionException;
use App\Modules\Alerts\Services\Exceptions\NoEligibleInstitutionException;
use App\Modules\Alerts\Support\RecordsCaseEvents;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Transmission institutionnelle (ecosystem/institutions/01 §6 ;
 * ecosystem/alertes/03 §1). Le dispatch en état `created` fait office de
 * ligne d'outbox transactionnelle (architecture/10 : « l'événement et
 * l'état source sont enregistrés dans la même transaction PostgreSQL ») ;
 * {@see transmitPending()} est le worker idempotent qui le fait passer à
 * `transmitted` (commande planifiée, jamais de Redis ni de WebSocket —
 * architecture/10 §Sobriété).
 */
class CaseDispatchService
{
    use RecordsCaseEvents;

    public function __construct(
        private readonly InstitutionRoutingProjection $routing,
    ) {}

    /**
     * @throws NoEligibleInstitutionException Aucune organisation éligible — jamais un routage inventé.
     */
    public function routeToEligibleInstitutions(AlertCase $case, string $correlationId): InstitutionDispatch
    {
        $organizations = $this->routing->eligibleFor($case->category, $case->country_code);

        $organization = $organizations->first();

        if ($organization === null) {
            throw new NoEligibleInstitutionException(
                "aucune institution éligible pour la catégorie {$case->category->value} au pays {$case->country_code}"
            );
        }

        return $this->createDispatch($case, $organization, $correlationId);
    }

    public function createDispatch(AlertCase $case, Organization $organization, string $correlationId): InstitutionDispatch
    {
        return DB::transaction(function () use ($case, $organization, $correlationId): InstitutionDispatch {
            $dispatch = InstitutionDispatch::create([
                'case_id' => $case->id,
                'organization_id' => $organization->id,
                'territory_code' => $case->territory_code,
                'category' => $case->category,
                'state' => DispatchState::Created,
                'correlation_id' => $correlationId,
            ]);

            $this->recordCaseEvent($case, 'dispatch_created', $correlationId, metadata: [
                'dispatch_id' => $dispatch->id,
                'organization_id' => $organization->id,
            ]);

            return $dispatch;
        });
    }

    /**
     * Worker idempotent (architecture/10) : fait passer chaque dispatch
     * `created` à `transmitted`. Aucune notification push : le portail
     * institutionnel lit cet état par rafraîchissement/polling.
     *
     * @return int nombre de dispatches transmis
     */
    public function transmitPending(int $limit = 100): int
    {
        $pending = InstitutionDispatch::query()
            ->where('state', DispatchState::Created)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($pending as $dispatch) {
            $correlationId = (string) Str::uuid();
            $dispatch = $this->transition($dispatch, DispatchState::Transmitted, 'dispatch_transmitted', $correlationId, extraAttributes: ['transmitted_at' => now()]);
            $this->cascadeToCase($dispatch, SosCaseState::Transmitted, $correlationId, null, $dispatch->organization);
            $count++;
        }

        return $count;
    }

    public function acknowledge(InstitutionDispatch $dispatch, PersonAccountLink $actor, Organization $organization, string $correlationId): InstitutionDispatch
    {
        $dispatch = $this->transition($dispatch, DispatchState::Received, 'dispatch_received', $correlationId, $actor, $organization, extraAttributes: ['received_at' => now()]);

        $this->cascadeToCase($dispatch, SosCaseState::Received, $correlationId, $actor, $organization);

        return $dispatch->fresh();
    }

    public function accept(InstitutionDispatch $dispatch, PersonAccountLink $actor, Organization $organization, string $correlationId): InstitutionDispatch
    {
        $dispatch = $this->transition($dispatch, DispatchState::Accepted, 'dispatch_accepted', $correlationId, $actor, $organization, extraAttributes: ['accepted_at' => now(), 'responsible_person_account_link_id' => $actor->id]);

        $this->cascadeToCase($dispatch, SosCaseState::Accepted, $correlationId, $actor, $organization);

        return $dispatch->fresh();
    }

    public function process(InstitutionDispatch $dispatch, PersonAccountLink $actor, Organization $organization, string $correlationId): InstitutionDispatch
    {
        $dispatch = $this->transition($dispatch, DispatchState::Processing, 'dispatch_processing', $correlationId, $actor, $organization, extraAttributes: ['processing_at' => now()]);

        $this->cascadeToCase($dispatch, SosCaseState::Processing, $correlationId, $actor, $organization);

        return $dispatch->fresh();
    }

    public function resolve(InstitutionDispatch $dispatch, PersonAccountLink $actor, Organization $organization, string $correlationId): InstitutionDispatch
    {
        $dispatch = $this->transition($dispatch, DispatchState::Resolved, 'dispatch_resolved', $correlationId, $actor, $organization, extraAttributes: ['resolved_at' => now()]);

        $this->cascadeToCase($dispatch, SosCaseState::Resolved, $correlationId, $actor, $organization);

        return $dispatch->fresh();
    }

    public function refuse(InstitutionDispatch $dispatch, PersonAccountLink $actor, Organization $organization, string $reason, string $correlationId): InstitutionDispatch
    {
        return $this->transition($dispatch, DispatchState::Refused, 'dispatch_refused', $correlationId, $actor, $organization, ['reason' => $reason], extraAttributes: ['error_detail' => $reason]);
    }

    /**
     * Un dossier n'est jamais routé deux fois activement vers la même
     * organisation (contrainte SQL `institution_dispatches_one_active_per_org`) :
     * transférer libère l'ancien dispatch avant d'en ouvrir un nouveau.
     */
    public function transfer(InstitutionDispatch $dispatch, PersonAccountLink $actor, Organization $fromOrganization, Organization $toOrganization, string $reason, string $correlationId): InstitutionDispatch
    {
        return DB::transaction(function () use ($dispatch, $actor, $fromOrganization, $toOrganization, $reason, $correlationId): InstitutionDispatch {
            $this->transition($dispatch, DispatchState::Transferred, 'dispatch_transferred', $correlationId, $actor, $fromOrganization, ['reason' => $reason, 'to_organization_id' => $toOrganization->id]);

            $case = $dispatch->case;
            $newDispatch = $this->createDispatch($case, $toOrganization, $correlationId);

            if ($case->nature->value === 'sos') {
                $this->transitionCase($case, SosCaseState::Transferred->value, 'case_transferred', $correlationId, actor: $actor, actorOrganization: $fromOrganization, metadata: ['to_organization_id' => $toOrganization->id]);
            }

            return $newDispatch;
        });
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $extraAttributes
     */
    private function transition(
        InstitutionDispatch $dispatch,
        DispatchState $to,
        string $eventType,
        string $correlationId,
        ?PersonAccountLink $actor = null,
        ?Organization $organization = null,
        array $metadata = [],
        array $extraAttributes = [],
    ): InstitutionDispatch {
        if (! in_array($to, $dispatch->state->allowedNextStates(), true)) {
            throw new InvalidCaseTransitionException("transition de dispatch {$to->value} refusée depuis l'état {$dispatch->state->value}");
        }

        $dispatch->forceFill([...$extraAttributes, 'state' => $to])->save();

        $this->recordCaseEvent($dispatch->case, $eventType, $correlationId, actor: $actor, actorOrganization: $organization, metadata: [...$metadata, 'dispatch_id' => $dispatch->id]);

        return $dispatch->fresh();
    }

    private function cascadeToCase(InstitutionDispatch $dispatch, SosCaseState $to, string $correlationId, ?PersonAccountLink $actor, ?Organization $organization): void
    {
        $case = $dispatch->case;

        if ($case->nature->value !== 'sos') {
            return;
        }

        $current = $case->sosState();

        if ($current === null || ! in_array($to, $current->allowedNextStates(), true)) {
            // Le dispatch a progressé, mais le dossier SOS a déjà atteint
            // un état incompatible (ex. déjà résolu par un autre canal) :
            // ne jamais forcer une transition impossible sur le dossier.
            return;
        }

        $this->transitionCase($case, $to->value, 'case_'.$to->value, $correlationId, actor: $actor, actorOrganization: $organization, metadata: ['dispatch_id' => $dispatch->id]);
    }
}
