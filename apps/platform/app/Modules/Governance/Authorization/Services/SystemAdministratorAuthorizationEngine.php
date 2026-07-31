<?php

namespace App\Modules\Governance\Authorization\Services;

use App\Modules\Governance\Authorization\Contracts\AuthorizationObligation;
use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Enums\PolicyState;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Support\ConditionsMatcher;
use App\Modules\Governance\Authorization\Support\InvalidConditionsPayloadException;
use App\Modules\Governance\Authorization\Support\ScopeMatcher;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Variante centrale du moteur qui donne au titulaire effectif du rôle
 * Administrateur Système toutes les capacités déclarées, présentes et
 * futures, sans exiger un grant supplémentaire pour chaque écran.
 *
 * L'override ne contourne pas les barrières structurelles : la capacité
 * demandée doit exister et couvrir l'opération, le compte et sa liaison
 * doivent être actifs, l'appartenance éventuellement fournie doit être
 * cohérente, le grant Administrateur Système et sa politique doivent être
 * effectifs, ses conditions doivent être satisfaites et chaque décision
 * reste auditée. Le niveau `strong` déclaré par le rôle demeure donc requis.
 */
final class SystemAdministratorAuthorizationEngine extends AuthorizationEngine
{
    private const SYSTEM_ADMINISTRATOR_CAPABILITY_KEY = 'governance.system_administrator';

    public function __construct(
        ScopeMatcher $scopeMatcher,
        private readonly ConditionsMatcher $systemAdministratorConditionsMatcher,
        private readonly AuditRecorder $systemAdministratorAuditRecorder,
    ) {
        parent::__construct(
            $scopeMatcher,
            $systemAdministratorConditionsMatcher,
            $systemAdministratorAuditRecorder,
        );
    }

    public function evaluate(AuthorizationRequest $request): AuthorizationResult
    {
        $outcome = $this->systemAdministratorOutcome($request);

        if ($outcome === null) {
            return parent::evaluate($request);
        }

        try {
            DB::transaction(function () use ($request, $outcome): void {
                $this->systemAdministratorAuditRecorder->recordAuthorizationDecision(
                    $request,
                    $outcome['result'],
                    $outcome['membershipId'],
                    $outcome['organizationId'],
                    $outcome['capabilityVersion'],
                    $outcome['policyVersion'],
                );
            });
        } catch (Throwable) {
            return AuthorizationResult::make(
                AuthorizationDecision::Denied,
                'audit_unavailable',
                'La décision n\'a pas pu être enregistrée ; l\'accès est refusé par prudence.',
                $request->correlationId,
            );
        }

        return $outcome['result'];
    }

    /**
     * @return array{result: AuthorizationResult, membershipId: ?string, organizationId: ?string, capabilityVersion: ?int, policyVersion: ?int}|null
     */
    private function systemAdministratorOutcome(AuthorizationRequest $request): ?array
    {
        $capability = CapabilityDefinition::query()
            ->where('stable_key', $request->capabilityKey)
            ->where('state', CapabilityState::Active->value)
            ->where('effective_from', '<=', $request->evaluatedAt)
            ->where(function ($query) use ($request): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $request->evaluatedAt);
            })
            ->first();

        if ($capability === null || $request->operation !== $capability->operation) {
            return null;
        }

        if ($request->assurance->accountState !== AccountState::Active) {
            return null;
        }

        $link = PersonAccountLink::query()->find($request->personAccountLinkId);

        if ($link === null || $link->status !== LinkStatus::Active || $link->user_id !== $request->accountUserId) {
            return null;
        }

        $membership = null;
        $organization = null;

        if ($request->membershipId !== null) {
            $membership = Membership::query()->with('organization')->find($request->membershipId);

            if ($membership === null
                || $membership->status !== MembershipStatus::Active
                || $membership->person_account_link_id !== $link->id) {
                return null;
            }

            $organization = $membership->organization;

            if ($organization === null || $organization->state !== OrganizationState::Active) {
                return null;
            }
        }

        $systemAdministratorGrant = Grant::query()
            ->with(['capabilityDefinition', 'policyVersion'])
            ->whereHas('personAccountLink', fn ($query) => $query->where('person_id', $link->person_id))
            ->whereHas('capabilityDefinition', function ($query) use ($request): void {
                $query
                    ->where('stable_key', self::SYSTEM_ADMINISTRATOR_CAPABILITY_KEY)
                    ->where('state', CapabilityState::Active->value)
                    ->where('effective_from', '<=', $request->evaluatedAt)
                    ->where(function ($period) use ($request): void {
                        $period->whereNull('effective_to')->orWhere('effective_to', '>', $request->evaluatedAt);
                    });
            })
            ->whereHas('policyVersion', function ($query) use ($request): void {
                $query
                    ->where('state', PolicyState::Active->value)
                    ->where('effective_from', '<=', $request->evaluatedAt)
                    ->where(function ($period) use ($request): void {
                        $period->whereNull('effective_to')->orWhere('effective_to', '>', $request->evaluatedAt);
                    });
            })
            ->where('effect', GrantEffect::Allow->value)
            ->where('state', GrantState::Active->value)
            ->where('valid_from', '<=', $request->evaluatedAt)
            ->where(function ($query) use ($request): void {
                $query->whereNull('valid_until')->orWhere('valid_until', '>', $request->evaluatedAt);
            })
            ->orderBy('id')
            ->first();

        if ($systemAdministratorGrant === null) {
            return null;
        }

        try {
            $conditions = $systemAdministratorGrant->conditions();
        } catch (InvalidConditionsPayloadException) {
            return null;
        }

        $conditionsResult = $this->systemAdministratorConditionsMatcher->evaluate(
            $conditions,
            $request->assurance,
            $systemAdministratorGrant->capabilityDefinition->minimum_session_assurance,
        );

        if (!$conditionsResult->satisfied) {
            if (!$conditionsResult->onlySessionAssuranceInsufficient) {
                return null;
            }

            return [
                'membershipId' => $membership?->id,
                'organizationId' => $organization?->id,
                'capabilityVersion' => $capability->version,
                'policyVersion' => $systemAdministratorGrant->policyVersion->version,
                'result' => AuthorizationResult::make(
                    AuthorizationDecision::StepUpRequired,
                    'system_administrator_session_assurance_insufficient',
                    'Une authentification forte est requise pour utiliser les pouvoirs de l\'Administrateur Système.',
                    $request->correlationId,
                    $systemAdministratorGrant->policyVersion->stable_key,
                    $systemAdministratorGrant->policyVersion->version,
                    capabilityKey: $capability->stable_key,
                    capabilityVersion: $capability->version,
                    obligations: [
                        new AuthorizationObligation('matched_grant', ['grant_id' => $systemAdministratorGrant->id]),
                        new AuthorizationObligation('system_administrator_override', ['grant_id' => $systemAdministratorGrant->id]),
                        new AuthorizationObligation('required_session_assurance', [
                            'minimum_session_assurance' => $conditionsResult->requiredSessionAssurance->value,
                        ]),
                    ],
                ),
            ];
        }

        return [
            'membershipId' => $membership?->id,
            'organizationId' => $organization?->id,
            'capabilityVersion' => $capability->version,
            'policyVersion' => $systemAdministratorGrant->policyVersion->version,
            'result' => AuthorizationResult::make(
                AuthorizationDecision::Allowed,
                'system_administrator_override',
                'Action autorisée par le rôle Administrateur Système, sans grant individuel supplémentaire.',
                $request->correlationId,
                $systemAdministratorGrant->policyVersion->stable_key,
                $systemAdministratorGrant->policyVersion->version,
                capabilityKey: $capability->stable_key,
                capabilityVersion: $capability->version,
                obligations: [
                    new AuthorizationObligation('matched_grant', ['grant_id' => $systemAdministratorGrant->id]),
                    new AuthorizationObligation('system_administrator_override', ['grant_id' => $systemAdministratorGrant->id]),
                ],
            ),
        ];
    }
}
