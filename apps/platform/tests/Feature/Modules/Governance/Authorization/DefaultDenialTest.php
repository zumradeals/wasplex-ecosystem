<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\CapabilityState;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\PolicyState;
use App\Modules\Governance\Authorization\Enums\PurposeState;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Support\AssuranceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class DefaultDenialTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_unknown_capability_is_denied(): void
    {
        $user = $this->makeUser('inconnu@example.com');

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'nonexistent.capability'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('unknown_or_inactive_capability', $result->reason->code);
    }

    public function test_inactive_capability_is_denied(): void
    {
        $user = $this->makeUser('inactive-cap@example.com');
        $this->makeCapability('sample.read', state: CapabilityState::Retired);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('unknown_or_inactive_capability', $result->reason->code);
    }

    public function test_grant_with_inactive_policy_is_denied(): void
    {
        $user = $this->makeUser('inactive-policy@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);

        // Politique active à la création, retirée juste après pour simuler
        // un grant existant devenu gouverné par une politique inactive.
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $policy->update(['state' => PolicyState::Retired]);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_matching_grant', $result->reason->code);
        $this->assertNotNull($grant);
    }

    public function test_subject_without_any_grant_is_denied(): void
    {
        $user = $this->makeUser('sans-grant@example.com');
        $this->makeCapability('sample.read');

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_suspended_grant_is_denied(): void
    {
        $user = $this->makeUser('suspendu@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        app(GrantManager::class)->suspend($grant, $link, 'test', (string) Str::uuid());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_revoked_grant_is_denied(): void
    {
        $user = $this->makeUser('revoque@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        app(GrantManager::class)->revoke($grant, $link, 'test', (string) Str::uuid());

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_expired_grant_is_denied_without_waiting_for_a_scheduled_task(): void
    {
        $user = $this->makeUser('expire@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        // L'état reste "active" en base ; seule l'échéance a expiré.
        $grant->forceFill(['valid_from' => now()->subMinutes(2), 'valid_until' => now()->subMinute()])->save();

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('no_active_grant', $result->reason->code);
    }

    public function test_missing_purpose_is_denied_when_required(): void
    {
        $user = $this->makeUser('sans-finalite@example.com');
        $capability = $this->makeCapability('sample.export', purposeRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $purpose = $this->makePurpose();
        $this->authorizePurpose($capability, $purpose);

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), purpose: $purpose);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.export'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_inactive_purpose_is_denied(): void
    {
        $user = $this->makeUser('finalite-inactive@example.com');
        $capability = $this->makeCapability('sample.export', purposeRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $purpose = $this->makePurpose();
        $this->authorizePurpose($capability, $purpose);
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), purpose: $purpose);

        $purpose->update(['state' => PurposeState::Retired]);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.export', purposeKey: $purpose->stable_key));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_purpose_not_authorized_for_capability_is_denied(): void
    {
        $user = $this->makeUser('finalite-interdite@example.com');
        $capability = $this->makeCapability('sample.export', purposeRequired: true);
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $authorizedPurpose = $this->makePurpose('authorized_purpose');
        $this->authorizePurpose($capability, $authorizedPurpose);
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), purpose: $authorizedPurpose);

        $otherPurpose = $this->makePurpose('other_purpose');

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.export', purposeKey: $otherPurpose->stable_key));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_incomplete_or_unknown_scope_format_is_denied(): void
    {
        $user = $this->makeUser('portee-invalide@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        // Version de schéma inconnue simulée directement en base.
        $grant->forceFill(['scope_schema_version' => 99])->save();

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_unknown_conditions_format_is_denied(): void
    {
        $user = $this->makeUser('conditions-invalides@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $grant = $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $grant->forceFill(['conditions_schema_version' => 99])->save();

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read'));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_non_active_account_is_denied(): void
    {
        $user = $this->makeUser('compte-suspendu@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $assurance = new AssuranceContext(
            accountState: AccountState::Suspended,
            contactAssurance: ContactAssurance::Unconfirmed,
            identityAssurance: IdentityAssurance::Undeclared,
            uniquenessAssurance: UniquenessAssurance::Unknown,
            organizationStatus: OrganizationStatus::None,
            sessionAssurance: SessionAssurance::Weak,
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest($user, 'sample.read', assurance: $assurance));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('account_not_active', $result->reason->code);
    }

    public function test_non_active_link_is_denied(): void
    {
        $user = $this->makeUser('liaison-inactive@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $linkId = $link->id;
        $link->forceFill(['status' => 'superseded'])->save();

        $result = app(AuthorizationEngine::class)->evaluate(
            $this->makeRequest($user, 'sample.read', personAccountLinkId: $linkId)
        );

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('account_link_not_active', $result->reason->code);
    }

    public function test_non_active_membership_is_denied(): void
    {
        $user = $this->makeUser('membre-inactif@example.com');
        $this->makeCapability('sample.read');
        $organization = $this->makeOrganization();
        $membership = $this->makeActiveMembership($user, $organization);
        $membership->update(['status' => 'suspended']);

        $result = app(AuthorizationEngine::class)->evaluate(
            $this->makeRequest($user, 'sample.read', membershipId: $membership->id)
        );

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('membership_not_active', $result->reason->code);
    }

    public function test_non_active_organization_is_denied(): void
    {
        $user = $this->makeUser('organisation-fermee@example.com');
        $this->makeCapability('sample.read');
        $organization = $this->makeOrganization();
        $membership = $this->makeActiveMembership($user, $organization);
        $organization->update(['state' => OrganizationState::Closed]);

        $result = app(AuthorizationEngine::class)->evaluate(
            $this->makeRequest($user, 'sample.read', membershipId: $membership->id)
        );

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('organization_not_active', $result->reason->code);
    }

    public function test_different_organization_scope_is_denied(): void
    {
        $user = $this->makeUser('org-differente@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $organizationA = $this->makeOrganization('Organisation A');
        $organizationB = $this->makeOrganization('Organisation B');
        $membership = $this->makeActiveMembership($user, $organizationA);

        $this->proposeAndActivateGrant(
            $membership,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['organization_id' => $organizationA->id]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            membershipId: $membership->id,
            resource: $this->makeResourceContext(organizationId: $organizationB->id),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_different_resource_is_denied(): void
    {
        $user = $this->makeUser('ressource-differente@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['resource_type' => 'invoice', 'resource_ids' => ['invoice-1']]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(resourceType: 'invoice', resourceId: 'invoice-2'),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }

    public function test_different_country_territory_or_environment_is_denied(): void
    {
        $user = $this->makeUser('pays-different@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        $this->proposeAndActivateGrant(
            $link,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray([
                'country_code' => 'CI',
                'territory_codes' => ['CI-AB'],
                'environment' => Environment::Production->value,
            ]),
        );

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(
                countryCode: 'SN',
                territoryCodes: ['SN-DK'],
                environment: Environment::Testing,
            ),
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
    }
}
