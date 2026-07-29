<?php

namespace Tests\Feature\Modules\Alerts;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\CaseNature;
use App\Modules\Alerts\Enums\CommunityCaseState;
use App\Modules\Alerts\Enums\SosCaseState;
use App\Modules\Alerts\Enums\VerificationLevel;
use App\Modules\Alerts\Models\AlertCase;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Models\PolicyVersion;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Support\Str;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

abstract class AlertsTestCase extends TestCase
{
    use StaffCapabilityTesting;

    protected function makeInstitution(string $countryCode = 'CI'): Organization
    {
        return Organization::create([
            'category' => OrganizationCategory::Institution,
            'legal_name' => 'Institution de test',
            'display_name' => 'Institution de test',
            'country_code' => $countryCode,
            'state' => OrganizationState::Active,
        ]);
    }

    /**
     * Octroie une capacité institutionnelle scopée organisation + catégories
     * (ecosystem/alertes/03 §1.4) via le vrai cycle GrantManager — jamais
     * une écriture directe.
     *
     * @param  list<CaseCategory>  $categories
     */
    protected function grantInstitutionalCapability(
        PersonAccountLink $subject,
        string $capabilityKey,
        Organization $organization,
        array $categories = [],
    ): Grant {
        $capability = CapabilityDefinition::query()
            ->where('stable_key', $capabilityKey)
            ->where('state', 'active')
            ->firstOrFail();

        $policy = PolicyVersion::create([
            'stable_key' => 'test_policy_'.$capabilityKey.'_'.Str::uuid(),
            'version' => 1,
            'state' => 'active',
            'checksum' => hash('sha256', $capabilityKey.random_int(1, PHP_INT_MAX)),
            'effective_from' => now(),
        ]);

        $author = $this->activeLinkFor($this->makeUser('grant-author-'.Str::uuid().'@example.com'));
        $approver = $this->activeLinkFor($this->makeUser('grant-approver-'.Str::uuid().'@example.com'));

        // Une portée `organization_id` exige un sujet porté par une
        // appartenance (`Membership`), pas une liaison individuelle seule
        // (GrantManager::assertSubjectOrganizationCoherence) — même modèle
        // que ecosystem/institutions/01 §3 (« chaque utilisateur
        // institutionnel agit sous une identité nominative liée à son
        // organisation »).
        $membership = Membership::query()->firstOrCreate(
            ['person_account_link_id' => $subject->id, 'organization_id' => $organization->id],
            ['status' => MembershipStatus::Active],
        );

        $scopePayload = ['organization_id' => $organization->id];

        if ($categories !== []) {
            $scopePayload['resource_type'] = 'alerts.category';
            $scopePayload['resource_ids'] = array_map(fn (CaseCategory $c): string => $c->value, $categories);
        }

        $manager = app(GrantManager::class);
        $correlationId = (string) Str::uuid();

        $grant = $manager->propose(
            subject: $membership,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray($scopePayload),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: $correlationId,
        );

        return $manager->activate($grant, $author, $approver, $correlationId);
    }

    protected function makeCommunityCase(
        ?PersonAccountLink $author = null,
        CaseCategory $category = CaseCategory::LostItem,
        CommunityCaseState $state = CommunityCaseState::Draft,
        string $countryCode = 'CI',
    ): AlertCase {
        return AlertCase::create([
            'author_person_account_link_id' => ($author ?? $this->makeRepresentative())->id,
            'nature' => CaseNature::Community,
            'category' => $category,
            'verification_level' => VerificationLevel::Unverified,
            'state' => $state->value,
            'country_code' => $countryCode,
            'source_description' => 'Description de test.',
        ]);
    }

    protected function makeSosCase(
        ?PersonAccountLink $author = null,
        CaseCategory $category = CaseCategory::Fire,
        SosCaseState $state = SosCaseState::Created,
        string $countryCode = 'CI',
    ): AlertCase {
        return AlertCase::create([
            'author_person_account_link_id' => $author?->id,
            'nature' => CaseNature::Sos,
            'category' => $category,
            'verification_level' => VerificationLevel::Unverified,
            'state' => $state->value,
            'country_code' => $countryCode,
            'source_description' => 'SOS de test.',
        ]);
    }
}
