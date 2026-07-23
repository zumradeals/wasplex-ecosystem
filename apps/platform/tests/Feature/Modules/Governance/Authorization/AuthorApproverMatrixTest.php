<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Enums\RiskClass;
use App\Modules\Governance\Authorization\Services\Exceptions\AuthorSubstitutionRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SelfAuthorizationRefusedException;
use App\Modules\Governance\Authorization\Services\Exceptions\SeparationOfDutiesViolationException;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Matrice complète des relations interdites entre sujet, auteur et
 * approbateur à l'activation d'un grant (TD-0001-A). Aucun acteur n'est
 * jamais l'unique contrôleur de sa propre habilitation, quelle que soit la
 * combinaison envisagée (Constitution art. 18 §9, §19).
 */
class AuthorApproverMatrixTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_activate_refuses_a_substituted_author(): void
    {
        $subject = $this->activeLinkFor($this->makeUser('matrix-substitution-subject@example.com'));
        $author = $this->makeAuthor();
        $impostor = $this->makeAuthor();
        $capability = $this->makeCapability('sample.matrix_substitution');
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(AuthorSubstitutionRefusedException::class);

        // Un tiers, jamais impliqué à la proposition, tente de s'attribuer
        // l'activation en se faisant passer pour l'auteur.
        app(GrantManager::class)->activate($grant, $impostor, null, (string) Str::uuid());
    }

    public function test_matrix_subject_equals_author_without_approver_is_refused(): void
    {
        $author = $this->makeAuthor();
        $capability = $this->makeCapability('sample.matrix_subject_author');
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $author,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SelfAuthorizationRefusedException::class);

        app(GrantManager::class)->activate($grant, $author, null, (string) Str::uuid());
    }

    public function test_matrix_approver_equals_author_is_refused(): void
    {
        $subject = $this->activeLinkFor($this->makeUser('matrix-approver-author-subject@example.com'));
        $author = $this->makeAuthor();
        $capability = $this->makeCapability('sample.matrix_approver_author');
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SeparationOfDutiesViolationException::class);

        // Approbateur = auteur, bien que le sujet en soit distinct.
        app(GrantManager::class)->activate($grant, $author, $author, (string) Str::uuid());
    }

    public function test_matrix_approver_equals_subject_is_refused_even_via_delegation(): void
    {
        $subject = $this->activeLinkFor($this->makeUser('matrix-approver-subject@example.com'));
        $author = $this->makeAuthor();
        $capability = $this->makeCapability('sample.matrix_approver_subject');
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SeparationOfDutiesViolationException::class);

        // Auteur bien distinct du sujet (délégation) : c'est l'approbateur
        // qui est ici identique au sujet, un cas non couvert avant TD-0001-A.
        app(GrantManager::class)->activate($grant, $author, $subject, (string) Str::uuid());
    }

    public function test_matrix_sensitive_capability_without_approver_is_refused(): void
    {
        $subject = $this->activeLinkFor($this->makeUser('matrix-sensitive-subject@example.com'));
        $author = $this->makeAuthor();
        $capability = $this->makeCapability('sample.matrix_sensitive', riskClass: RiskClass::Sensitive);
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->expectException(SeparationOfDutiesViolationException::class);

        app(GrantManager::class)->activate($grant, $author, null, (string) Str::uuid());
    }

    public function test_matrix_coherent_delegation_with_a_fourth_distinct_approver_succeeds(): void
    {
        $subject = $this->activeLinkFor($this->makeUser('matrix-coherent-subject@example.com'));
        $author = $this->makeAuthor();
        $approver = $this->makeAuthor();
        $capability = $this->makeCapability('sample.matrix_coherent');
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $subject,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $author,
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        // Sujet, auteur et approbateur sont trois personnes distinctes :
        // aucune relation interdite de la matrice n'est atteinte.
        $activated = app(GrantManager::class)->activate($grant, $author, $approver, (string) Str::uuid());

        $this->assertNotNull($activated->activated_at);
    }
}
