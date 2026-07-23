<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Models\User;
use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Enums\PolicyState;
use App\Modules\Governance\Authorization\Enums\PurposeState;
use App\Modules\Governance\Authorization\Enums\RiskClass;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\CapabilityPurpose;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Models\PurposeDefinition;
use App\Modules\Governance\Authorization\Models\RoleTemplate;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use App\Modules\Identity\Support\AssuranceContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AuthorizationTestCase extends TestCase
{
    protected function makeUser(string $email): User
    {
        return app(RegistersUserIdentity::class)->register([
            'name' => 'Utilisateur '.$email,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    protected function activeLinkFor(User $user): PersonAccountLink
    {
        return PersonAccountLink::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    /**
     * Un acteur nominatif distinct du sujet, utilisé comme auteur ou
     * approbateur dans les tests : l'auteur d'un grant ne peut jamais être
     * son propre sujet sans approbateur distinct (P003-B1 §12).
     */
    protected function makeAuthor(): PersonAccountLink
    {
        return $this->activeLinkFor($this->makeUser('auteur-'.Str::uuid().'@example.com'));
    }

    protected function makeOrganization(string $displayName = 'Organisation Test'): Organization
    {
        return Organization::create([
            'category' => OrganizationCategory::Advertiser,
            'legal_name' => $displayName.' SARL',
            'display_name' => $displayName,
            'country_code' => 'CI',
            'state' => OrganizationState::Active,
        ]);
    }

    protected function makeActiveMembership(User $user, Organization $organization): Membership
    {
        return Membership::create([
            'person_account_link_id' => $this->activeLinkFor($user)->id,
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  list<string>  $forbiddenExtraSegment
     */
    protected function makeCapability(
        string $stableKey = 'sample.read',
        RiskClass $riskClass = RiskClass::Ordinary,
        bool $purposeRequired = false,
        bool $approvalRequired = false,
        SessionAssurance $minimumSessionAssurance = SessionAssurance::Weak,
        CapabilityState $state = CapabilityState::Active,
        int $version = 1,
        Operation $operation = Operation::Read,
        ?CarbonInterface $effectiveFrom = null,
        ?CarbonInterface $effectiveTo = null,
    ): CapabilityDefinition {
        [$domain, $action] = explode('.', $stableKey);

        return CapabilityDefinition::create([
            'stable_key' => $stableKey,
            'version' => $version,
            'domain' => $domain,
            'action' => $action,
            'description' => 'Capacité de test.',
            'operation' => $operation,
            'risk_class' => $riskClass,
            'purpose_required' => $purposeRequired,
            'approval_required' => $approvalRequired,
            'minimum_session_assurance' => $minimumSessionAssurance,
            'state' => $state,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);
    }

    protected function makePurpose(
        string $stableKey = 'test_purpose',
        PurposeState $state = PurposeState::Active,
        ?CarbonInterface $effectiveFrom = null,
        ?CarbonInterface $effectiveTo = null,
    ): PurposeDefinition {
        return PurposeDefinition::create([
            'stable_key' => $stableKey,
            'version' => 1,
            'description' => 'Finalité de test.',
            'state' => $state,
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);
    }

    /**
     * Le catalogue de finalités d'une capacité active est figé en base
     * (P003-B1.3 §5) : cet assistant reproduit le cycle de vie réel
     * (attache pendant que la capacité est draft, puis réactivation) pour
     * que les tests puissent continuer à composer capacité et finalité dans
     * n'importe quel ordre sans contourner la protection.
     */
    protected function authorizePurpose(CapabilityDefinition $capability, PurposeDefinition $purpose): void
    {
        $originalState = $capability->state;

        if ($originalState === CapabilityState::Active) {
            $capability->forceFill(['state' => CapabilityState::Draft])->save();
        }

        CapabilityPurpose::create([
            'capability_definition_id' => $capability->id,
            'purpose_definition_id' => $purpose->id,
        ]);

        if ($originalState === CapabilityState::Active) {
            $capability->forceFill(['state' => CapabilityState::Active])->save();
        }
    }

    protected function makePolicy(
        string $stableKey = 'test_policy',
        PolicyState $state = PolicyState::Active,
        ?CarbonInterface $effectiveFrom = null,
        ?CarbonInterface $effectiveTo = null,
    ): PolicyVersion {
        return PolicyVersion::create([
            'stable_key' => $stableKey,
            'version' => 1,
            'state' => $state,
            'checksum' => hash('sha256', $stableKey.random_int(1, PHP_INT_MAX)),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
        ]);
    }

    protected function proposeAndActivateGrant(
        PersonAccountLink|Membership $subject,
        CapabilityDefinition $capability,
        PolicyVersion $policy,
        PersonAccountLink $author,
        ?PersonAccountLink $approver = null,
        ?ScopePayload $scope = null,
        ?ConditionsPayload $conditions = null,
        GrantEffect $effect = GrantEffect::Allow,
        ?PurposeDefinition $purpose = null,
        ?RoleTemplate $roleTemplate = null,
        ?CarbonInterface $validFrom = null,
        ?CarbonInterface $validUntil = null,
    ): Grant {
        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: $scope ?? ScopePayload::fromArray(['self' => true]),
            conditions: $conditions ?? ConditionsPayload::fromArray([]),
            effect: $effect,
            source: GrantSource::Direct,
            author: $author,
            purpose: $purpose,
            roleTemplate: $roleTemplate,
            sourceReference: null,
            validFrom: $validFrom ?? now(),
            validUntil: $validUntil,
            correlationId: $correlationId,
        );

        return $manager->activate($grant, $author, $approver, $correlationId);
    }

    /**
     * Insère un grant directement en base, hors de tout passage par
     * {@see GrantManager}. Réservé aux scénarios simulant une donnée déjà
     * corrompue ou historique (format de portée/conditions inconnu, sujet
     * malformé) : une fois créé, un grant ne peut plus voir ses champs
     * sémantiques modifiés par UPDATE (P003-B1.3 §4), donc seule une
     * insertion directe permet de reproduire ces cas en test.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function insertRawGrant(
        PersonAccountLink|Membership $subject,
        CapabilityDefinition $capability,
        PolicyVersion $policy,
        PersonAccountLink $author,
        array $overrides = [],
    ): string {
        $id = (string) Str::uuid();

        DB::table('governance.grants')->insert(array_merge([
            'id' => $id,
            'person_account_link_id' => $subject instanceof PersonAccountLink ? $subject->id : null,
            'membership_id' => $subject instanceof Membership ? $subject->id : null,
            'capability_definition_id' => $capability->id,
            'policy_version_id' => $policy->id,
            'scope_schema_version' => ScopePayload::SCHEMA_VERSION,
            'scope_payload' => json_encode(['self' => true], JSON_FORCE_OBJECT),
            'conditions_schema_version' => ConditionsPayload::SCHEMA_VERSION,
            'conditions_payload' => json_encode([], JSON_FORCE_OBJECT),
            'effect' => GrantEffect::Allow->value,
            'state' => GrantState::Active->value,
            'source' => GrantSource::Direct->value,
            'valid_from' => now(),
            'author_person_account_link_id' => $author->id,
            'activated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return $id;
    }

    protected function makeResourceContext(
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?string $organizationId = null,
        ?string $ownerPersonId = null,
        ?string $countryCode = null,
        array $territoryCodes = [],
        ?Environment $environment = null,
    ): ResourceContext {
        return new ResourceContext(
            resourceType: $resourceType,
            resourceId: $resourceId,
            organizationId: $organizationId,
            ownerPersonId: $ownerPersonId,
            countryCode: $countryCode,
            territoryCodes: $territoryCodes,
            environment: $environment,
        );
    }

    protected function makeRequest(
        User $user,
        string $capabilityKey,
        ?string $membershipId = null,
        ?string $purposeKey = null,
        ?ResourceContext $resource = null,
        Operation $operation = Operation::Read,
        ?AssuranceContext $assurance = null,
        ?string $personAccountLinkId = null,
    ): AuthorizationRequest {
        // Permet de simuler une liaison désormais inactive : activeLinkFor()
        // ne la retrouverait plus une fois son statut changé.
        $linkId = $personAccountLinkId ?? $this->activeLinkFor($user)->id;

        $assurance ??= new AssuranceContext(
            accountState: AccountState::Active,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Weak,
        );

        return new AuthorizationRequest(
            accountUserId: $user->id,
            personAccountLinkId: $linkId,
            membershipId: $membershipId,
            capabilityKey: $capabilityKey,
            purposeKey: $purposeKey,
            resource: $resource ?? $this->makeResourceContext(),
            operation: $operation,
            countryCode: null,
            territoryCodes: [],
            environment: Environment::Testing,
            assurance: $assurance,
            correlationId: (string) Str::uuid(),
            evaluatedAt: now(),
        );
    }
}
