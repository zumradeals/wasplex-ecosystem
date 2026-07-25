<?php

namespace App\Modules\Governance\Authorization\Services;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Models\RoleTemplate;
use App\Modules\Governance\Authorization\Models\RoleTemplateCapability;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\SystemIdentity;
use LogicException;

/**
 * Émet automatiquement, pour un sujet donné, les grants proposés par un
 * rôle modèle déjà déclaré (P007 — ferme TD-0005-D et TD-0004-E pour les
 * seules capacités listées ci-dessous). Un rôle modèle seul n'autorise
 * jamais rien (ADR-0004 §6) : cette classe est le seul endroit de
 * l'écosystème qui transforme une proposition de rôle en grants
 * effectivement activés, sans intervention humaine — l'auteur de
 * référence est donc toujours le compte technique interne
 * ({@see SystemIdentity}), jamais une personne réelle.
 *
 * N'émet jamais deux fois le même grant pour un même sujet : un grant actif
 * déjà existant pour cette capacité est laissé tel quel (idempotent).
 *
 * La portée de chaque capacité est un fait du domaine, pas une donnée du
 * rôle modèle (`RoleTemplateCapability` ne porte aucune portée) : elle est
 * donc résolue explicitement ici, capacité par capacité, plutôt que
 * devinée génériquement.
 */
class GrantAutoIssuer
{
    private const POLICY_STABLE_KEY = 'automatic_role_template_grants';

    /**
     * Capacités dont la portée réelle est `self` sur la personne elle-même
     * (ownerPersonId = son propre person_id).
     *
     * @var list<string>
     */
    private const SELF_SCOPED_CAPABILITIES = [
        'wallet.view',
        'campaign.create',
        'campaign.submit_for_review',
        'campaign.view',
        'advertiser_profile.create',
    ];

    /**
     * Capacités largement disponibles à tout sujet authentifié, sur
     * n'importe quelle ressource du type indiqué (jamais `self` : voir le
     * raisonnement documenté sur `campaign.report`, migration
     * `2026_07_25_100006`).
     *
     * @var array<string, string>
     */
    private const BROAD_RESOURCE_TYPE_CAPABILITIES = [
        'campaign.report' => 'advertising.campaign',
    ];

    public function __construct(
        private readonly GrantManager $grantManager,
        private readonly SystemIdentity $systemIdentity,
    ) {}

    public function issueRoleTemplateGrants(PersonAccountLink $subject, string $roleTemplateStableKey, string $correlationId): void
    {
        $roleTemplate = RoleTemplate::query()
            ->where('stable_key', $roleTemplateStableKey)
            ->where('state', 'active')
            ->firstOrFail();

        $capabilities = RoleTemplateCapability::query()
            ->where('role_template_id', $roleTemplate->id)
            ->with('capabilityDefinition')
            ->get();

        foreach ($capabilities as $roleTemplateCapability) {
            $this->issueOne($subject, $roleTemplateCapability->capabilityDefinition, $roleTemplate, $correlationId);
        }
    }

    private function issueOne(PersonAccountLink $subject, CapabilityDefinition $capability, RoleTemplate $roleTemplate, string $correlationId): void
    {
        $alreadyActive = Grant::query()
            ->where('person_account_link_id', $subject->id)
            ->where('capability_definition_id', $capability->id)
            ->where('state', GrantState::Active)
            ->exists();

        if ($alreadyActive) {
            return;
        }

        $author = $this->systemIdentity->provisioningAccount();
        $policy = $this->policy();
        $scope = $this->scopeFor($capability, $subject);

        $grant = $this->grantManager->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: $scope,
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::RoleTemplate,
            author: $author,
            purpose: null,
            roleTemplate: $roleTemplate,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: $correlationId,
        );

        $this->grantManager->activate($grant, $author, null, $correlationId);
    }

    private function scopeFor(CapabilityDefinition $capability, PersonAccountLink $subject): ScopePayload
    {
        if (in_array($capability->stable_key, self::SELF_SCOPED_CAPABILITIES, true)) {
            return ScopePayload::fromArray(['self' => true]);
        }

        if (array_key_exists($capability->stable_key, self::BROAD_RESOURCE_TYPE_CAPABILITIES)) {
            return ScopePayload::fromArray(['resource_type' => self::BROAD_RESOURCE_TYPE_CAPABILITIES[$capability->stable_key]]);
        }

        throw new LogicException("GrantAutoIssuer ne connaît aucune portée pour la capacité « {$capability->stable_key} » — l'ajouter explicitement avant de la référencer dans un rôle modèle automatique.");
    }

    private function policy(): PolicyVersion
    {
        return PolicyVersion::query()
            ->where('stable_key', self::POLICY_STABLE_KEY)
            ->where('state', 'active')
            ->firstOrFail();
    }
}
