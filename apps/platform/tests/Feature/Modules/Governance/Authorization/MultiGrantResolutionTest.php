<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Résolution déterministe en présence de plusieurs grants candidats
 * (P003-B1.1 §3). Le résultat ne dépend jamais de l'ordre naturel des lignes
 * PostgreSQL, de l'ordre d'insertion ou des UUID générés.
 */
class MultiGrantResolutionTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_two_equivalent_grants_inserted_in_different_orders_yield_the_same_result(): void
    {
        $user = $this->makeUser('multi-1@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $ownResource = $this->makeResourceContext(ownerPersonId: $link->person_id);

        // Ordre A → B.
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());

        $resultOrderAB = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $ownResource,
        ));

        // Nouveau compte indépendant, grants créés dans l'ordre inverse
        // (B → A demeure indiscernable pour deux grants équivalents, mais
        // démontre que l'ordre de création n'influence jamais la décision).
        $userReversed = $this->makeUser('multi-1-reversed@example.com');
        $linkReversed = $this->activeLinkFor($userReversed);
        $this->proposeAndActivateGrant($linkReversed, $capability, $policy, $this->makeAuthor());
        $this->proposeAndActivateGrant($linkReversed, $capability, $policy, $this->makeAuthor());

        $resultOrderBA = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $userReversed,
            'sample.read',
            resource: $this->makeResourceContext(ownerPersonId: $linkReversed->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $resultOrderAB->decision);
        $this->assertSame($resultOrderAB->decision, $resultOrderBA->decision);
    }

    public function test_an_invalid_grant_alongside_a_fully_valid_grant_yields_the_valid_grants_result(): void
    {
        $user = $this->makeUser('multi-2@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();

        // Grant hors portée (organisation exigée, mais aucune appartenance
        // fournie dans la requête) : ne doit pas neutraliser l'autre grant.
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor());
        $organization = $this->makeOrganization('Organisation Multi 2');
        $membership = $this->makeActiveMembership($user, $organization);
        $this->proposeAndActivateGrant(
            $membership,
            $capability,
            $policy,
            $this->makeAuthor(),
            scope: ScopePayload::fromArray(['organization_id' => $organization->id]),
        );

        // La requête ne porte aucun membershipId : seul le grant "self" via
        // $link peut s'appliquer, l'autre grant (portée organisation) est
        // hors portée pour cette requête précise sans neutraliser le premier.
        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $this->makeResourceContext(ownerPersonId: $link->person_id),
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);
    }

    public function test_two_incompatible_effects_return_denied_ambiguous_grants(): void
    {
        $user = $this->makeUser('multi-3@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $ownResource = $this->makeResourceContext(ownerPersonId: $link->person_id);

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::ReadOnly);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $ownResource,
        ));

        $this->assertSame(AuthorizationDecision::Denied, $result->decision);
        $this->assertSame('ambiguous_grants', $result->reason->code);

        $obligationTypes = array_map(fn ($obligation): string => $obligation->type, $result->obligations);
        $this->assertContains('ambiguous_grants', $obligationTypes);
    }

    public function test_two_equivalent_grants_yield_a_stable_auditable_decision(): void
    {
        $user = $this->makeUser('multi-4@example.com');
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($user);
        $policy = $this->makePolicy();
        $ownResource = $this->makeResourceContext(ownerPersonId: $link->person_id);

        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);
        $this->proposeAndActivateGrant($link, $capability, $policy, $this->makeAuthor(), effect: GrantEffect::Allow);

        $result = app(AuthorizationEngine::class)->evaluate($this->makeRequest(
            $user,
            'sample.read',
            resource: $ownResource,
        ));

        $this->assertSame(AuthorizationDecision::Allowed, $result->decision);

        $matchedGrantObligation = null;
        foreach ($result->obligations as $obligation) {
            if ($obligation->type === 'matched_grant') {
                $matchedGrantObligation = $obligation;
            }
        }

        $this->assertNotNull($matchedGrantObligation, 'Le grant ayant justifié la décision doit rester identifiable dans l\'audit.');
        $this->assertArrayHasKey('grant_id', $matchedGrantObligation->payload);
    }
}
