<?php

namespace Tests\Feature\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationsAndMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email): User
    {
        return app(RegistersUserIdentity::class)->register([
            'name' => 'Utilisateur '.$email,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    private function makeOrganization(OrganizationCategory $category, string $displayName): Organization
    {
        return Organization::create([
            'category' => $category,
            'legal_name' => $displayName.' SARL',
            'display_name' => $displayName,
            'country_code' => $category === OrganizationCategory::Wasplex ? null : 'CI',
        ]);
    }

    public function test_membership_grants_no_implicit_role(): void
    {
        $user = $this->makeUser('membre@example.com');
        $organization = $this->makeOrganization(OrganizationCategory::Advertiser, 'Annonceur Test');
        $person = Person::create();

        Membership::create([
            'person_id' => $person->id,
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'status' => MembershipStatus::Active,
        ]);

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();

        // La simple création d'une appartenance n'élève jamais automatiquement
        // le statut de représentation d'organisation du compte (ADR-0004 §5).
        $this->assertSame(OrganizationStatus::None, $assurance->organization_status);
    }

    public function test_advertiser_organization_cannot_see_another_organizations_memberships(): void
    {
        $userA = $this->makeUser('membre-a@example.com');
        $userB = $this->makeUser('membre-b@example.com');

        $orgA = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation A');
        $orgB = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation B');

        Membership::create([
            'person_id' => Person::create()->id,
            'user_id' => $userA->id,
            'organization_id' => $orgA->id,
            'status' => MembershipStatus::Active,
        ]);

        Membership::create([
            'person_id' => Person::create()->id,
            'user_id' => $userB->id,
            'organization_id' => $orgB->id,
            'status' => MembershipStatus::Active,
        ]);

        $visibleToOrgA = Membership::query()->forOrganization($orgA->id)->pluck('user_id');

        $this->assertEquals([$userA->id], $visibleToOrgA->all());
        $this->assertNotContains($userB->id, $visibleToOrgA->all());
    }

    public function test_organization_categories_are_exact_and_exclude_agent(): void
    {
        $values = OrganizationCategory::values();

        sort($values);

        $this->assertSame(['advertiser', 'institution', 'wasplex'], $values);
        $this->assertNotContains('agent', $values);
    }
}
