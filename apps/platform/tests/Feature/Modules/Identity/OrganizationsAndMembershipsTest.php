<?php

namespace Tests\Feature\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationState;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /**
     * Retourne la liaison active réellement créée par l'inscription, plutôt
     * qu'une personne sans rapport avec le compte (revue SIRR P003-A.2 §1).
     */
    private function activeLinkFor(User $user): PersonAccountLink
    {
        return PersonAccountLink::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function test_membership_references_the_real_person_account_link(): void
    {
        $user = $this->makeUser('membre@example.com');
        $link = $this->activeLinkFor($user);
        $organization = $this->makeOrganization(OrganizationCategory::Advertiser, 'Annonceur Test');

        $membership = Membership::create([
            'person_account_link_id' => $link->id,
            'organization_id' => $organization->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->assertSame($link->id, $membership->personAccountLink->id);
        $this->assertSame($user->id, $membership->personAccountLink->user_id);
        $this->assertSame($link->person_id, $membership->personAccountLink->person_id);
    }

    public function test_membership_grants_no_implicit_role(): void
    {
        $user = $this->makeUser('membre-role@example.com');
        $link = $this->activeLinkFor($user);
        $organization = $this->makeOrganization(OrganizationCategory::Advertiser, 'Annonceur Test 2');

        Membership::create([
            'person_account_link_id' => $link->id,
            'organization_id' => $organization->id,
            'status' => MembershipStatus::Active,
        ]);

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();

        // La simple création d'une appartenance n'élève jamais automatiquement
        // le statut de représentation d'organisation du compte (ADR-0004 §5).
        $this->assertSame(OrganizationStatus::None, $assurance->organization_status);
    }

    public function test_for_organization_scope_filters_memberships_by_organization(): void
    {
        // Membership::forOrganization() est un filtre de requête pratique.
        // Ce n'est pas une frontière d'autorisation : Membership::query()
        // reste une lecture non filtrée, techniquement possible depuis ce
        // module. L'isolation effective côté serveur est différée à P003-B.
        $userA = $this->makeUser('membre-a@example.com');
        $userB = $this->makeUser('membre-b@example.com');

        $orgA = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation A');
        $orgB = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation B');

        $membershipA = Membership::create([
            'person_account_link_id' => $this->activeLinkFor($userA)->id,
            'organization_id' => $orgA->id,
            'status' => MembershipStatus::Active,
        ]);

        Membership::create([
            'person_account_link_id' => $this->activeLinkFor($userB)->id,
            'organization_id' => $orgB->id,
            'status' => MembershipStatus::Active,
        ]);

        $filtered = Membership::query()->forOrganization($orgA->id)->pluck('id');

        $this->assertEquals([$membershipA->id], $filtered->all());
    }

    public function test_membership_referencing_a_nonexistent_link_is_refused_by_postgres(): void
    {
        $organization = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation C');

        $this->expectException(QueryException::class);

        DB::table('identity.memberships')->insert([
            'id' => (string) Str::uuid7(),
            'person_account_link_id' => (string) Str::uuid7(),
            'organization_id' => $organization->id,
            'status' => 'active',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_two_identical_active_memberships_for_the_same_link_and_organization_are_refused(): void
    {
        $user = $this->makeUser('membre-doublon@example.com');
        $link = $this->activeLinkFor($user);
        $organization = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation D');

        Membership::create([
            'person_account_link_id' => $link->id,
            'organization_id' => $organization->id,
            'status' => MembershipStatus::Active,
        ]);

        $this->expectException(QueryException::class);

        DB::table('identity.memberships')->insert([
            'id' => (string) Str::uuid7(),
            'person_account_link_id' => $link->id,
            'organization_id' => $organization->id,
            'status' => 'active',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_organization_with_a_membership_cannot_be_deleted(): void
    {
        $user = $this->makeUser('membre-suppression@example.com');
        $link = $this->activeLinkFor($user);
        $organization = $this->makeOrganization(OrganizationCategory::Advertiser, 'Organisation E');

        $membership = Membership::create([
            'person_account_link_id' => $link->id,
            'organization_id' => $organization->id,
            'status' => MembershipStatus::Active,
        ]);

        try {
            // Isolée dans sa propre transaction (savepoint) afin que l'échec
            // attendu n'invalide pas la transaction englobante du test.
            DB::transaction(function () use ($organization): void {
                DB::table('identity.organizations')->where('id', $organization->id)->delete();
            });
            $this->fail('A QueryException was expected: organization_id is restricted, not cascaded.');
        } catch (QueryException) {
            // attendu : la contrainte restrictive refuse la suppression.
        }

        // L'organisation et son appartenance subsistent toutes les deux :
        // aucune suppression en cascade silencieuse ne s'est produite.
        $this->assertTrue(Organization::query()->whereKey($organization->id)->exists());
        $this->assertTrue(Membership::query()->whereKey($membership->id)->exists());

        // La fermeture d'une organisation ayant des appartenances passe par
        // son état métier explicite (`closed`), jamais par une suppression
        // physique destructive.
        $organization->update(['state' => OrganizationState::Closed]);

        $this->assertSame(
            OrganizationState::Closed,
            $organization->fresh()->state,
        );
        $this->assertTrue(Membership::query()->whereKey($membership->id)->exists());
    }

    public function test_organization_categories_are_exact_and_exclude_agent(): void
    {
        $values = OrganizationCategory::values();

        sort($values);

        $this->assertSame(['advertiser', 'institution', 'wasplex'], $values);
        $this->assertNotContains('agent', $values);
    }
}
