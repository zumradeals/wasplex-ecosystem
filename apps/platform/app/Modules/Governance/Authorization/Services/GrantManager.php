<?php

namespace App\Modules\Governance\Authorization\Services;

use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantEventType;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Enums\PolicyState;
use App\Modules\Governance\Authorization\Enums\PurposeState;
use App\Modules\Governance\Authorization\Enums\RiskClass;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\CapabilityPurpose;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Models\PurposeDefinition;
use App\Modules\Governance\Authorization\Models\RoleTemplate;
use App\Modules\Governance\Authorization\Services\Exceptions\AuthorSubstitutionRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\CapabilityNotAvailableException;
use App\Modules\Governance\Authorization\Services\Exceptions\GrantNotProposedException;
use App\Modules\Governance\Authorization\Services\Exceptions\PolicyNotAvailableException;
use App\Modules\Governance\Authorization\Services\Exceptions\PurposeNotAuthorizedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SelfAuthorizationRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SeparationOfDutiesViolationException;
use App\Modules\Governance\Authorization\Services\Exceptions\SubjectOrganizationMismatchException;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\InvalidScopePayloadException;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cycle d'attribution des grants (P003-B1 §12). Une activation ne modifie
 * jamais un ancien grant : chaque décision produit un nouvel état persistant
 * et un événement d'audit.
 */
class GrantManager
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    /**
     * @param  PersonAccountLink|Membership  $subject  Exactement un sujet humain (P003-B1 §9).
     */
    public function propose(
        PersonAccountLink|Membership $subject,
        CapabilityDefinition $capability,
        PolicyVersion $policy,
        ScopePayload $scope,
        ConditionsPayload $conditions,
        GrantEffect $effect,
        GrantSource $source,
        PersonAccountLink $author,
        ?PurposeDefinition $purpose,
        ?RoleTemplate $roleTemplate,
        ?string $sourceReference,
        ?CarbonInterface $validFrom,
        ?CarbonInterface $validUntil,
        string $correlationId,
    ): Grant {
        $this->assertCapabilityActive($capability);
        $this->assertPolicyActive($policy);
        $this->assertPurposeValid($capability, $purpose);
        $this->assertSubjectOrganizationCoherence($subject, $scope);
        $this->assertScopeEffectCoherence($scope, $effect);

        return DB::transaction(function () use (
            $subject, $capability, $policy, $scope, $conditions, $effect, $source,
            $author, $purpose, $roleTemplate, $sourceReference, $validFrom, $validUntil, $correlationId,
        ): Grant {
            $grant = Grant::create([
                'person_account_link_id' => $subject instanceof PersonAccountLink ? $subject->id : null,
                'membership_id' => $subject instanceof Membership ? $subject->id : null,
                'capability_definition_id' => $capability->id,
                'purpose_definition_id' => $purpose?->id,
                'policy_version_id' => $policy->id,
                'role_template_id' => $roleTemplate?->id,
                'scope_schema_version' => ScopePayload::SCHEMA_VERSION,
                'scope_payload' => $scope->toArray(),
                'conditions_schema_version' => ConditionsPayload::SCHEMA_VERSION,
                'conditions_payload' => $conditions->toArray(),
                'effect' => $effect,
                'state' => GrantState::Proposed,
                'source' => $source,
                'source_reference' => $sourceReference,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'author_person_account_link_id' => $author->id,
            ]);

            $this->auditRecorder->recordGrantEvent($grant, $author, GrantEventType::Proposed, $correlationId);

            return $grant;
        });
    }

    /**
     * Matrice complète des relations interdites entre sujet, auteur et
     * approbateur (TD-0001-A) :
     *
     *  - l'auteur transmis ici doit être exactement celui enregistré à la
     *    proposition ({@see propose()}) : aucune substitution d'auteur
     *    n'est jamais possible entre les deux appels ;
     *  - sujet = auteur, sans approbateur distinct : refusé
     *    (auto-habilitation) ;
     *  - approbateur = auteur : refusé, y compris lorsque le sujet diffère
     *    de l'auteur (délégation) ;
     *  - approbateur = sujet : refusé, y compris lorsque l'auteur diffère
     *    du sujet (délégation) ;
     *  - capacité sensitive ou critical sans approbateur : refusé.
     *
     * Aucun acteur n'est donc jamais l'unique contrôleur de sa propre
     * habilitation, quelle que soit la combinaison sujet/auteur envisagée
     * (Constitution art. 18 §9, §19).
     *
     * @throws AuthorSubstitutionRefusedException L'auteur transmis diffère de celui enregistré à la proposition.
     * @throws GrantNotProposedException Le grant n'est plus au stade `proposed` (aucune réactivation ni activation répétée).
     * @throws SelfAuthorizationRefusedException Auteur = sujet sans approbateur distinct.
     * @throws SeparationOfDutiesViolationException Approbateur requis (sensitive/critical), identique à l'auteur, ou identique au sujet.
     */
    public function activate(Grant $grant, PersonAccountLink $author, ?PersonAccountLink $approver, string $correlationId): Grant
    {
        if ($grant->state !== GrantState::Proposed) {
            throw new GrantNotProposedException(
                "seul un grant à l'état proposed peut être activé ; état actuel : {$grant->state->value}"
            );
        }

        if ($grant->author_person_account_link_id !== $author->id) {
            throw new AuthorSubstitutionRefusedException(
                "l'auteur transmis à l'activation ne correspond pas à l'auteur enregistré à la proposition"
            );
        }

        $capability = $grant->capabilityDefinition;
        $policy = $grant->policyVersion;

        $this->assertCapabilityActive($capability);
        $this->assertPolicyActive($policy);

        $subjectPersonId = $this->resolveSubjectPersonId($grant);
        $authorPersonId = $author->person_id;

        if ($approver === null && $subjectPersonId === $authorPersonId) {
            throw new SelfAuthorizationRefusedException(
                "l'auteur ne peut créer et activer seul sa propre habilitation"
            );
        }

        if (in_array($capability->risk_class, [RiskClass::Sensitive, RiskClass::Critical], true) && $approver === null) {
            throw new SeparationOfDutiesViolationException(
                'les capacités sensitive et critical exigent un approbateur distinct'
            );
        }

        if ($approver !== null && $approver->person_id === $authorPersonId) {
            throw new SeparationOfDutiesViolationException(
                "l'auteur ne peut être son propre approbateur"
            );
        }

        if ($approver !== null && $approver->person_id === $subjectPersonId) {
            throw new SeparationOfDutiesViolationException(
                "l'approbateur ne peut être le sujet de l'habilitation"
            );
        }

        return DB::transaction(function () use ($grant, $author, $approver, $correlationId): Grant {
            $grant->forceFill([
                'state' => GrantState::Active,
                'activated_at' => now(),
                'approver_person_account_link_id' => $approver?->id,
            ])->save();

            $this->auditRecorder->recordGrantEvent(
                $grant->fresh(),
                $approver ?? $author,
                GrantEventType::Activated,
                $correlationId,
            );

            return $grant->fresh();
        });
    }

    public function suspend(Grant $grant, PersonAccountLink $actor, string $reason, string $correlationId): Grant
    {
        return DB::transaction(function () use ($grant, $actor, $reason, $correlationId): Grant {
            $grant->forceFill(['state' => GrantState::Suspended])->save();

            $this->auditRecorder->recordGrantEvent($grant->fresh(), $actor, GrantEventType::Suspended, $correlationId, $reason);

            return $grant->fresh();
        });
    }

    /**
     * Une révocation est définitive : le grant ne redevient jamais actif par
     * simple mise à jour (garanti en base par un déclencheur, P003-B1 §9).
     */
    public function revoke(Grant $grant, PersonAccountLink $actor, string $reason, string $correlationId): Grant
    {
        return DB::transaction(function () use ($grant, $actor, $reason, $correlationId): Grant {
            $grant->forceFill([
                'state' => GrantState::Revoked,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ])->save();

            $this->auditRecorder->recordGrantEvent($grant->fresh(), $actor, GrantEventType::Revoked, $correlationId, $reason);

            return $grant->fresh();
        });
    }

    /**
     * Constate qu'un grant est arrivé à expiration et matérialise son état.
     * Le moteur d'autorisation ne dépend jamais de cet appel : il vérifie
     * `valid_until` directement à chaque évaluation (P003-B1 §12).
     */
    public function markExpiredIfDue(Grant $grant, PersonAccountLink $actor, string $correlationId): Grant
    {
        if (! in_array($grant->state, [GrantState::Active, GrantState::Proposed, GrantState::Suspended], true)) {
            return $grant;
        }

        if (! $grant->isExpiredByTime(now())) {
            return $grant;
        }

        return DB::transaction(function () use ($grant, $actor, $correlationId): Grant {
            $grant->forceFill(['state' => GrantState::Expired])->save();

            $this->auditRecorder->recordGrantEvent($grant->fresh(), $actor, GrantEventType::Expired, $correlationId);

            return $grant->fresh();
        });
    }

    private function assertCapabilityActive(CapabilityDefinition $capability): void
    {
        if ($capability->state !== CapabilityState::Active) {
            throw new CapabilityNotAvailableException("capacité inactive : {$capability->stable_key}");
        }
    }

    private function assertPolicyActive(PolicyVersion $policy): void
    {
        if ($policy->state !== PolicyState::Active) {
            throw new PolicyNotAvailableException("politique inactive : {$policy->stable_key}");
        }
    }

    private function assertPurposeValid(CapabilityDefinition $capability, ?PurposeDefinition $purpose): void
    {
        if (! $capability->purpose_required) {
            return;
        }

        if ($purpose === null) {
            throw new PurposeNotAuthorizedException('finalité requise pour cette capacité');
        }

        if ($purpose->state !== PurposeState::Active) {
            throw new PurposeNotAuthorizedException("finalité inactive : {$purpose->stable_key}");
        }

        $authorized = CapabilityPurpose::query()
            ->where('capability_definition_id', $capability->id)
            ->where('purpose_definition_id', $purpose->id)
            ->exists();

        if (! $authorized) {
            throw new PurposeNotAuthorizedException("finalité non autorisée pour cette capacité : {$purpose->stable_key}");
        }
    }

    /**
     * Un `organization_id` de portée doit toujours correspondre exactement
     * à l'organisation réelle de l'appartenance portant le grant : une
     * liaison individuelle sans appartenance ne peut jamais recevoir une
     * portée organisationnelle (P003-B1.1 §2). Réciproquement, un sujet
     * porté par une appartenance exige TOUJOURS une portée déclarant
     * l'organization_id de cette appartenance : une habilitation
     * organisationnelle sans cette restriction explicite ne serait jamais
     * détectable comme telle par le moteur (P003-B1.3 §2). Toute
     * incohérence est refusée dès la proposition, avant toute autorisation.
     */
    private function assertSubjectOrganizationCoherence(PersonAccountLink|Membership $subject, ScopePayload $scope): void
    {
        if ($subject instanceof Membership) {
            if ($scope->organizationId === null) {
                throw new SubjectOrganizationMismatchException(
                    "un sujet porté par une appartenance exige toujours une portée déclarant l'organization_id de cette appartenance"
                );
            }

            if ($subject->organization_id !== $scope->organizationId) {
                throw new SubjectOrganizationMismatchException(
                    "l'organization_id de la portée ne correspond pas à l'organisation réelle de l'appartenance"
                );
            }

            return;
        }

        if ($scope->organizationId !== null) {
            throw new SubjectOrganizationMismatchException(
                'une portée déclarant organization_id exige un sujet porté par une appartenance, pas une liaison individuelle seule'
            );
        }
    }

    /**
     * Un effet `masked` n'a de sens que rapporté à une liste `fields`
     * explicite ; réciproquement, `fields` n'a de sens que pour un effet
     * `masked` — l'associer à `allow` ou `read_only` laisserait croire à une
     * restriction de champs jamais appliquée par le moteur (P003-B1.3 §1).
     */
    private function assertScopeEffectCoherence(ScopePayload $scope, GrantEffect $effect): void
    {
        if ($effect === GrantEffect::Masked && ($scope->fields === null || $scope->fields === [])) {
            throw new InvalidScopePayloadException('un effet masked exige une liste "fields" non vide dans la portée');
        }

        if ($effect !== GrantEffect::Masked && $scope->fields !== null) {
            throw new InvalidScopePayloadException('la clé "fields" de la portée est refusée pour tout effet autre que masked');
        }
    }

    private function resolveSubjectPersonId(Grant $grant): string
    {
        if ($grant->person_account_link_id !== null) {
            return $grant->personAccountLink->person_id;
        }

        return $grant->membership->personAccountLink->person_id;
    }
}
