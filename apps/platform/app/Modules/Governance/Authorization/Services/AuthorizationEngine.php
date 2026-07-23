<?php

namespace App\Modules\Governance\Authorization\Services;

use App\Modules\Governance\Authorization\Contracts\AuthorizationObligation;
use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Enums\PolicyState;
use App\Modules\Governance\Authorization\Enums\PurposeState;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\CapabilityPurpose;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Support\ConditionsMatcher;
use App\Modules\Governance\Authorization\Support\InvalidConditionsPayloadException;
use App\Modules\Governance\Authorization\Support\InvalidScopePayloadException;
use App\Modules\Governance\Authorization\Support\ScopeMatcher;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Point d'entrée public unique du moteur d'autorisation (P003-B1 §3, §16).
 *
 * À refus par défaut, déterministe, explicable et identique quel que soit
 * l'appelant (contrôleur, commande, worker). Ambiguïté, donnée absente ou
 * format inconnu entraînent toujours un refus.
 */
class AuthorizationEngine
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ConditionsMatcher $conditionsMatcher,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function evaluate(AuthorizationRequest $request): AuthorizationResult
    {
        $outcome = $this->decide($request);

        try {
            DB::transaction(function () use ($request, $outcome): void {
                $this->auditRecorder->recordAuthorizationDecision(
                    $request,
                    $outcome['result'],
                    $outcome['membershipId'],
                    $outcome['organizationId'],
                    $outcome['capabilityVersion'],
                    $outcome['policyVersion'],
                );
            });
        } catch (Throwable) {
            // Une décision sensible qui ne peut pas être auditée échoue
            // fermée : jamais transformée silencieusement en autorisation
            // (P003-B1 §17).
            return AuthorizationResult::make(
                AuthorizationDecision::Denied,
                'audit_unavailable',
                "La décision n'a pas pu être enregistrée ; l'accès est refusé par prudence.",
                $request->correlationId,
            );
        }

        return $outcome['result'];
    }

    /**
     * @return array{result: AuthorizationResult, membershipId: ?string, organizationId: ?string, capabilityVersion: ?int, policyVersion: ?int}
     */
    private function decide(AuthorizationRequest $request): array
    {
        $empty = ['membershipId' => null, 'organizationId' => null, 'capabilityVersion' => null, 'policyVersion' => null];

        // 1. Capacité inconnue, inactive ou hors période d'effet. Une
        // capacité active mais future ou échue est refusée au même titre
        // qu'une capacité inconnue (P003-B1.3 §5).
        $capability = CapabilityDefinition::query()
            ->where('stable_key', $request->capabilityKey)
            ->where('state', CapabilityState::Active->value)
            ->where('effective_from', '<=', $request->evaluatedAt)
            ->where(function ($query) use ($request): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $request->evaluatedAt);
            })
            ->first();

        if ($capability === null) {
            return [...$empty, 'result' => $this->denied($request, 'unknown_or_inactive_capability', "Cette capacité n'existe pas, n'est plus active, ou n'est pas en période d'effet.")];
        }

        $empty['capabilityVersion'] = $capability->version;

        // 2. Opération atomique de la capacité : une capacité de lecture ne
        // peut jamais autoriser une écriture, et réciproquement ; un export
        // exige une capacité dédiée. Vérifié avant tout effet de grant, quel
        // qu'il soit — aucun effet `allow` ne contourne cette barrière
        // (P003-B1.3 §3).
        if ($request->operation !== $capability->operation) {
            return [...$empty, 'result' => $this->denied($request, 'operation_mismatch', "Cette capacité ne couvre pas l'opération demandée.", $capability)];
        }

        // 3. Compte, liaison active et assurances.
        if ($request->assurance->accountState !== AccountState::Active) {
            return [...$empty, 'result' => $this->denied($request, 'account_not_active', "Le compte n'est pas actif.")];
        }

        $link = PersonAccountLink::query()->find($request->personAccountLinkId);

        if ($link === null || $link->status !== LinkStatus::Active || $link->user_id !== $request->accountUserId) {
            return [...$empty, 'result' => $this->denied($request, 'account_link_not_active', "La liaison entre la personne et le compte n'est pas active.")];
        }

        // 4. Organisation/appartenance demandée, jamais fiée sans vérification.
        $membership = null;
        $organization = null;

        if ($request->membershipId !== null) {
            $membership = Membership::query()->with('organization')->find($request->membershipId);

            if ($membership === null
                || $membership->status !== MembershipStatus::Active
                || $membership->person_account_link_id !== $link->id) {
                return [...$empty, 'result' => $this->denied($request, 'membership_not_active', "L'appartenance n'est pas active ou ne correspond pas à ce compte.")];
            }

            $organization = $membership->organization;

            if ($organization === null || $organization->state !== OrganizationState::Active) {
                return [...$empty, 'result' => $this->denied($request, 'organization_not_active', "L'organisation n'est pas active.")];
            }
        }

        $empty['membershipId'] = $membership?->id;
        $empty['organizationId'] = $organization?->id;

        // 5-6. Grants actifs du sujet, non suspendus/révoqués/expirés/hors période.
        $candidates = Grant::query()
            ->where('capability_definition_id', $capability->id)
            ->where('state', GrantState::Active->value)
            ->where(function ($query) use ($link, $membership): void {
                $query->where('person_account_link_id', $link->id);

                if ($membership !== null) {
                    $query->orWhere('membership_id', $membership->id);
                }
            })
            ->where('valid_from', '<=', $request->evaluatedAt)
            ->where(function ($query) use ($request): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $request->evaluatedAt);
            })
            ->orderBy('created_at')
            ->get();

        if ($candidates->isEmpty()) {
            return [...$empty, 'result' => $this->denied($request, 'no_active_grant', 'Aucun droit actif ne couvre cette capacité.')];
        }

        $sawStepUpCandidate = false;
        $lastPolicyKey = null;
        $lastPolicyVersion = null;

        /** @var list<array{grant: Grant, policy: PolicyVersion, result: AuthorizationResult}> $qualifying */
        $qualifying = [];

        foreach ($candidates as $grant) {
            // 2 (grant). Politique absente, inactive ou hors période, propre
            // à ce grant.
            $policy = $grant->policyVersion;

            if ($policy === null || $policy->state !== PolicyState::Active) {
                continue;
            }

            if (! $this->isWithinPeriod($policy->effective_from, $policy->effective_to, $request->evaluatedAt)) {
                continue;
            }

            $lastPolicyKey = $policy->stable_key;
            $lastPolicyVersion = $policy->version;

            // 7. Finalité : état, période, et actualité de la liaison
            // capability_purposes (une capacité active peut avoir vu son
            // catalogue de finalités évoluer depuis la proposition du grant,
            // P003-B1.3 §5).
            if ($capability->purpose_required) {
                if ($request->purposeKey === null || $grant->purposeDefinition === null) {
                    continue;
                }

                $purpose = $grant->purposeDefinition;

                if ($purpose->stable_key !== $request->purposeKey || $purpose->state !== PurposeState::Active) {
                    continue;
                }

                if (! $this->isWithinPeriod($purpose->effective_from, $purpose->effective_to, $request->evaluatedAt)) {
                    continue;
                }

                $purposeCurrentlyLinked = CapabilityPurpose::query()
                    ->where('capability_definition_id', $capability->id)
                    ->where('purpose_definition_id', $purpose->id)
                    ->exists();

                if (! $purposeCurrentlyLinked) {
                    continue;
                }
            }

            // 8. Portée. Un grant hors portée ne neutralise jamais un autre
            // grant pleinement valide : on continue simplement l'évaluation
            // des autres candidats (P003-B1.1 §3).
            try {
                $scope = $grant->scope();
            } catch (InvalidScopePayloadException) {
                continue;
            }

            // Isolation organisationnelle, défense en profondeur (P003-B1.3
            // §2) : un grant porté par une appartenance sans organization_id
            // de portée est invalide et refusé ; l'organisation du grant et
            // celle de l'appartenance active résolue doivent coïncider
            // exactement. Une ressource organisationnelle n'est par ailleurs
            // jamais couverte par un grant strictement individuel.
            if ($grant->membership_id !== null) {
                if ($scope->organizationId === null) {
                    continue;
                }

                if ($organization === null || $scope->organizationId !== $organization->id) {
                    continue;
                }
            }

            if ($request->resource->organizationId !== null && $grant->membership_id === null) {
                continue;
            }

            $subjectPersonId = $membership !== null ? $membership->personAccountLink->person_id : $link->person_id;

            if (! $this->scopeMatcher->matches($scope, $request->resource, $subjectPersonId)) {
                continue;
            }

            // 9. Compatibilité entre l'effet du grant et l'opération
            // demandée, vérifiée AVANT les conditions de session : un grant
            // dont l'effet ne pourrait de toute façon jamais couvrir cette
            // opération ne doit jamais être signalé comme candidat de
            // renforcement de session (P003-B1.3 §6).
            if (! $this->effectIsCompatibleWithOperation($grant->effect, $request->operation)) {
                continue;
            }

            // 10. Conditions d'assurance (contact, identité, unicité, statut
            // organisationnel, puis session en dernier), plancher de session
            // de la capacité inclus : un grant ne peut jamais l'abaisser
            // (P003-B1.1 §1). À ce stade, portée, organisation, finalité,
            // période et compatibilité d'opération sont déjà acquises pour
            // ce grant : une insuffisance non liée à la session est un refus
            // définitif ; une insuffisance isolée de session est un
            // candidat de renforcement légitime (P003-B1.3a §priorité).
            try {
                $conditions = $grant->conditions();
            } catch (InvalidConditionsPayloadException) {
                continue;
            }

            $conditionsResult = $this->conditionsMatcher->evaluate(
                $conditions,
                $request->assurance,
                $capability->minimum_session_assurance,
            );

            if (! $conditionsResult->satisfied) {
                if ($conditionsResult->onlySessionAssuranceInsufficient) {
                    $sawStepUpCandidate = true;
                }

                continue;
            }

            // 11. Approbation d'action obligatoire : retournée seulement
            // lorsque toutes les autres conditions, y compris le niveau de
            // session, sont déjà satisfaites — jamais au profit d'une
            // identité insuffisamment qualifiée ou d'une session faible
            // (P003-B1.3a §priorité). P003-B1 ne possède pas encore de
            // preuve d'approbation, donc jamais "allowed" ici.
            if ($capability->approval_required) {
                return [
                    ...$empty,
                    'policyVersion' => $policy->version,
                    'result' => AuthorizationResult::make(
                        AuthorizationDecision::ApprovalRequired,
                        'approval_required',
                        'Cette action exige une approbation distincte avant exécution.',
                        $request->correlationId,
                        $policy->stable_key,
                        $policy->version,
                        capabilityKey: $capability->stable_key,
                        capabilityVersion: $capability->version,
                    ),
                ];
            }

            $qualifying[] = [
                'grant' => $grant,
                'policy' => $policy,
                'result' => $this->buildEffectResult($request, $capability, $policy, $grant, $scope),
            ];
        }

        if ($qualifying === []) {
            if ($sawStepUpCandidate) {
                return [
                    ...$empty,
                    'policyVersion' => $lastPolicyVersion,
                    'result' => AuthorizationResult::make(
                        AuthorizationDecision::StepUpRequired,
                        'session_assurance_insufficient',
                        'Une authentification plus forte est requise pour cette action.',
                        $request->correlationId,
                        $lastPolicyKey,
                        $lastPolicyVersion,
                        capabilityKey: $capability->stable_key,
                        capabilityVersion: $capability->version,
                    ),
                ];
            }

            return [...$empty, 'policyVersion' => $lastPolicyVersion, 'result' => $this->denied($request, 'no_matching_grant', 'Aucun droit actif ne correspond à cette demande.', $capability)];
        }

        // Résolution déterministe, indépendante de l'ordre PostgreSQL, de
        // l'ordre d'insertion ou des UUID générés (P003-B1.1 §3).
        $signatures = array_unique(array_map(
            fn (array $entry): string => $entry['result']->decision->value.'|'.json_encode($entry['result']->allowedFields),
            $qualifying,
        ));

        if (count($signatures) > 1) {
            $grantIds = array_map(fn (array $entry): string => $entry['grant']->id, $qualifying);
            sort($grantIds);

            return [
                ...$empty,
                'policyVersion' => $lastPolicyVersion,
                'result' => AuthorizationResult::make(
                    AuthorizationDecision::Denied,
                    'ambiguous_grants',
                    "Plusieurs droits actifs s'appliquent avec des effets incompatibles ; l'accès est refusé par prudence.",
                    $request->correlationId,
                    capabilityKey: $capability->stable_key,
                    capabilityVersion: $capability->version,
                    obligations: [new AuthorizationObligation('ambiguous_grants', ['grant_ids' => $grantIds])],
                ),
            ];
        }

        usort($qualifying, fn (array $a, array $b): int => $a['grant']->id <=> $b['grant']->id);
        $chosen = $qualifying[0];

        $chosenResult = $chosen['result'];
        $obligations = [
            ...$chosenResult->obligations,
            new AuthorizationObligation('matched_grant', ['grant_id' => $chosen['grant']->id]),
        ];

        return [
            ...$empty,
            'policyVersion' => $chosen['policy']->version,
            'result' => new AuthorizationResult(
                decision: $chosenResult->decision,
                reason: $chosenResult->reason,
                policyKey: $chosenResult->policyKey,
                policyVersion: $chosenResult->policyVersion,
                obligations: $obligations,
                validUntil: $chosenResult->validUntil,
                correlationId: $chosenResult->correlationId,
                allowedFields: $chosenResult->allowedFields,
                capabilityKey: $chosenResult->capabilityKey,
                capabilityVersion: $chosenResult->capabilityVersion,
            ),
        ];
    }

    private function isWithinPeriod(CarbonInterface $effectiveFrom, ?CarbonInterface $effectiveTo, CarbonInterface $evaluatedAt): bool
    {
        if ($evaluatedAt->lessThan($effectiveFrom)) {
            return false;
        }

        return $effectiveTo === null || $evaluatedAt->lessThan($effectiveTo);
    }

    /**
     * Un effet `read_only` ou `masked` ne couvre jamais autre chose qu'une
     * lecture ; `allow` ne restreint pas l'opération par lui-même mais reste
     * toujours borné, en amont, par l'opération déclarée de la capacité
     * (P003-B1.3 §3, §6).
     */
    private function effectIsCompatibleWithOperation(GrantEffect $effect, Operation $operation): bool
    {
        return match ($effect) {
            GrantEffect::Allow => true,
            GrantEffect::ReadOnly, GrantEffect::Masked => $operation === Operation::Read,
        };
    }

    /**
     * Construit le résultat d'un grant déjà reconnu qualifiant : portée,
     * organisation, finalité, période, opération et compatibilité d'effet
     * ont toutes déjà été vérifiées par l'appelant.
     */
    private function buildEffectResult(
        AuthorizationRequest $request,
        CapabilityDefinition $capability,
        PolicyVersion $policy,
        Grant $grant,
        ScopePayload $scope,
    ): AuthorizationResult {
        return match ($grant->effect) {
            GrantEffect::Allow => AuthorizationResult::make(
                AuthorizationDecision::Allowed,
                'allowed',
                'Action autorisée.',
                $request->correlationId,
                $policy->stable_key,
                $policy->version,
                capabilityKey: $capability->stable_key,
                capabilityVersion: $capability->version,
            ),
            GrantEffect::ReadOnly => AuthorizationResult::make(
                AuthorizationDecision::AllowedReadOnly,
                'allowed_read_only',
                'Action autorisée en lecture seule.',
                $request->correlationId,
                $policy->stable_key,
                $policy->version,
                capabilityKey: $capability->stable_key,
                capabilityVersion: $capability->version,
            ),
            GrantEffect::Masked => AuthorizationResult::make(
                AuthorizationDecision::AllowedMasked,
                'allowed_masked',
                'Action autorisée avec certains champs masqués.',
                $request->correlationId,
                $policy->stable_key,
                $policy->version,
                obligations: [new AuthorizationObligation('field_mask', ['fields' => $scope->fields ?? []])],
                allowedFields: $scope->fields ?? [],
                capabilityKey: $capability->stable_key,
                capabilityVersion: $capability->version,
            ),
        };
    }

    private function denied(
        AuthorizationRequest $request,
        string $reasonCode,
        string $explanation,
        ?CapabilityDefinition $capability = null,
    ): AuthorizationResult {
        return AuthorizationResult::make(
            AuthorizationDecision::Denied,
            $reasonCode,
            $explanation,
            $request->correlationId,
            capabilityKey: $capability?->stable_key,
            capabilityVersion: $capability?->version,
        );
    }
}
