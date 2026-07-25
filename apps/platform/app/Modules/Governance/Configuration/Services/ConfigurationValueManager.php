<?php

namespace App\Modules\Governance\Configuration\Services;

use App\Modules\Governance\Configuration\Enums\ApprovalDecision;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueVersionState;
use App\Modules\Governance\Configuration\Models\Activation;
use App\Modules\Governance\Configuration\Models\Approval;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Models\ValueVersion;
use App\Modules\Governance\Configuration\Services\Exceptions\DefinitionNotAvailableException;
use App\Modules\Governance\Configuration\Services\Exceptions\DuplicateApprovalException;
use App\Modules\Governance\Configuration\Services\Exceptions\InsufficientApprovalsException;
use App\Modules\Governance\Configuration\Services\Exceptions\SelfApprovalRefusedException;
use App\Modules\Governance\Configuration\Services\Exceptions\ValueVersionNotInStateException;
use App\Modules\Governance\Configuration\Support\ValueConstraints;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;

/**
 * Cycle d'une `ValueVersion` (ADR-0002 §4, §8), sur le modèle exact de
 * `GrantManager` (P003-B1 §12) et `CampaignVersionService` (ADR-0010).
 * Une activation ne modifie jamais une ancienne version : chaque décision
 * matérielle produit une nouvelle ligne ou un nouvel enregistrement d'audit
 * (`Approval`, `Activation`), jamais une mutation rétroactive.
 */
class ConfigurationValueManager
{
    /**
     * @throws DefinitionNotAvailableException La definition n'est pas active.
     */
    public function propose(Definition $definition, mixed $value, string $justification, PersonAccountLink $author): ValueVersion
    {
        $this->assertDefinitionActive($definition);

        ValueConstraints::assertValid($definition->value_type, $definition->constraints, $value);

        $nextVersion = 1 + (int) ValueVersion::query()
            ->where('definition_id', $definition->id)
            ->max('version');

        return ValueVersion::create([
            'definition_id' => $definition->id,
            'version' => $nextVersion,
            'value' => $value,
            'justification' => $justification,
            'state' => ValueVersionState::Draft,
            'author_person_account_link_id' => $author->id,
        ]);
    }

    /**
     * @throws ValueVersionNotInStateException La version n'est pas à l'état draft.
     */
    public function submitForReview(ValueVersion $version): ValueVersion
    {
        $this->assertState($version, [ValueVersionState::Draft]);

        $version->forceFill(['state' => ValueVersionState::InReview])->save();

        return $version->fresh();
    }

    /**
     * Enregistre une décision d'approbation ; transite automatiquement vers
     * `approved` dès que le nombre d'approbations actives requis par le
     * niveau de la definition est atteint (ADR-0002 §3.2-§3.4).
     *
     * @throws ValueVersionNotInStateException La version n'est pas à l'état in_review.
     * @throws SelfApprovalRefusedException L'approbateur est l'auteur de la version.
     * @throws DuplicateApprovalException L'approbateur a déjà décidé pour cette version.
     */
    public function approve(ValueVersion $version, PersonAccountLink $approver, ?string $motif = null): ValueVersion
    {
        $this->assertState($version, [ValueVersionState::InReview]);
        $this->assertNotAuthor($version, $approver);
        $this->assertNoExistingDecision($version, $approver);

        return DB::transaction(function () use ($version, $approver, $motif): ValueVersion {
            Approval::create([
                'value_version_id' => $version->id,
                'approver_person_account_link_id' => $approver->id,
                'decision' => ApprovalDecision::Approved,
                'motif' => $motif,
            ]);

            $required = $version->definition->level->requiredApprovals();
            $approvedCount = Approval::query()
                ->where('value_version_id', $version->id)
                ->where('decision', ApprovalDecision::Approved)
                ->count();

            if ($approvedCount >= $required) {
                $version->forceFill(['state' => ValueVersionState::Approved])->save();
            }

            return $version->fresh();
        });
    }

    /**
     * Un seul refus bloque la version (ADR-0002 §4 : « rejetée »). Aucune
     * annulation silencieuse des approbations déjà enregistrées : elles
     * restent un fait historique sur `Approval`, la version elle-même
     * devient simplement `rejected`.
     *
     * @throws ValueVersionNotInStateException La version n'est pas à l'état in_review.
     * @throws SelfApprovalRefusedException Le réviseur est l'auteur de la version.
     * @throws DuplicateApprovalException Le réviseur a déjà décidé pour cette version.
     */
    public function reject(ValueVersion $version, PersonAccountLink $reviewer, string $motif): ValueVersion
    {
        $this->assertState($version, [ValueVersionState::InReview]);
        $this->assertNotAuthor($version, $reviewer);
        $this->assertNoExistingDecision($version, $reviewer);

        return DB::transaction(function () use ($version, $reviewer, $motif): ValueVersion {
            Approval::create([
                'value_version_id' => $version->id,
                'approver_person_account_link_id' => $reviewer->id,
                'decision' => ApprovalDecision::Rejected,
                'motif' => $motif,
            ]);

            $version->forceFill([
                'state' => ValueVersionState::Rejected,
                'rejected_at' => now(),
                'rejection_reason' => $motif,
            ])->save();

            return $version->fresh();
        });
    }

    /**
     * Active la version : la précédente version active de la même
     * definition (s'il en existe une) devient `replaced` — transition
     * d'état seule, son contenu reste intact et consultable (ADR-0002 §8).
     *
     * @throws ValueVersionNotInStateException La version n'est pas à l'état approved.
     * @throws SelfApprovalRefusedException L'activateur est l'auteur de la version.
     * @throws InsufficientApprovalsException Le nombre d'approbations requis n'est plus atteint (défense en profondeur).
     */
    public function activate(ValueVersion $version, PersonAccountLink $activator, string $correlationId): ValueVersion
    {
        $this->assertState($version, [ValueVersionState::Approved]);
        $this->assertNotAuthor($version, $activator);

        $required = $version->definition->level->requiredApprovals();
        $approvedCount = Approval::query()
            ->where('value_version_id', $version->id)
            ->where('decision', ApprovalDecision::Approved)
            ->count();

        if ($approvedCount < $required) {
            throw new InsufficientApprovalsException(
                "cette definition exige {$required} approbation(s) active(s) ; {$approvedCount} enregistrée(s)"
            );
        }

        return DB::transaction(function () use ($version, $activator, $correlationId): ValueVersion {
            $previousActive = ValueVersion::query()
                ->where('definition_id', $version->definition_id)
                ->where('state', ValueVersionState::Active)
                ->first();

            if ($previousActive !== null) {
                $previousActive->forceFill([
                    'state' => ValueVersionState::Replaced,
                    'replaced_at' => now(),
                ])->save();
            }

            $version->forceFill(['state' => ValueVersionState::Active])->save();

            Activation::create([
                'value_version_id' => $version->id,
                'activated_by_person_account_link_id' => $activator->id,
                'correlation_id' => $correlationId,
                'replaced_value_version_id' => $previousActive?->id,
            ]);

            return $version->fresh();
        });
    }

    /**
     * « Annulée avant effet » (ADR-0002 §4) : une version draft, in_review
     * ou approved peut être retirée sans jamais avoir pris effet. Une
     * version déjà active ne peut être annulée — seule `activate()` d'une
     * nouvelle version la fait passer à `replaced`.
     *
     * @throws ValueVersionNotInStateException La version est active, replaced, rejected ou déjà cancelled.
     */
    public function cancel(ValueVersion $version): ValueVersion
    {
        $this->assertState($version, [
            ValueVersionState::Draft,
            ValueVersionState::InReview,
            ValueVersionState::Approved,
        ]);

        $version->forceFill(['state' => ValueVersionState::Cancelled])->save();

        return $version->fresh();
    }

    private function assertDefinitionActive(Definition $definition): void
    {
        if ($definition->state !== DefinitionState::Active) {
            throw new DefinitionNotAvailableException("definition inactive : {$definition->stable_key}");
        }
    }

    /**
     * @param  list<ValueVersionState>  $allowed
     */
    private function assertState(ValueVersion $version, array $allowed): void
    {
        if (! in_array($version->state, $allowed, true)) {
            $expected = implode('/', array_map(fn (ValueVersionState $s): string => $s->value, $allowed));

            throw new ValueVersionNotInStateException(
                "cette opération exige l'état {$expected} ; état actuel : {$version->state->value}"
            );
        }
    }

    private function assertNotAuthor(ValueVersion $version, PersonAccountLink $actor): void
    {
        if ($version->author_person_account_link_id === $actor->id) {
            throw new SelfApprovalRefusedException(
                "l'auteur d'une ValueVersion ne peut ni l'approuver, ni la refuser, ni l'activer (ADR-0002 §3.2, §3.3)"
            );
        }
    }

    private function assertNoExistingDecision(ValueVersion $version, PersonAccountLink $approver): void
    {
        $exists = Approval::query()
            ->where('value_version_id', $version->id)
            ->where('approver_person_account_link_id', $approver->id)
            ->exists();

        if ($exists) {
            throw new DuplicateApprovalException('cet approbateur a déjà décidé pour cette ValueVersion');
        }
    }
}
