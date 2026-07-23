<?php

namespace Tests\Feature\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\GrantEffect;
use App\Modules\Governance\Authorization\Enums\GrantSource;
use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ConditionsPayload;
use App\Modules\Governance\Authorization\Support\InvalidScopePayloadException;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Cohérence entre l'effet d'un grant et la présence de `fields` dans sa
 * portée (P003-B1.3 §1) : un effet `masked` exige toujours une liste de
 * champs explicite, et réciproquement, `fields` n'a de sens pour aucun autre
 * effet.
 */
class ScopeEffectCoherenceTest extends AuthorizationTestCase
{
    use RefreshDatabase;

    public function test_masked_effect_without_fields_is_refused_at_proposal(): void
    {
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($this->makeUser('portee-effet-1@example.com'));
        $policy = $this->makePolicy();

        $this->expectException(InvalidScopePayloadException::class);

        app(GrantManager::class)->propose(
            subject: $link,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Masked,
            source: GrantSource::Direct,
            author: $this->makeAuthor(),
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_fields_on_a_non_masked_effect_is_refused_at_proposal(): void
    {
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($this->makeUser('portee-effet-2@example.com'));
        $policy = $this->makePolicy();

        $this->expectException(InvalidScopePayloadException::class);

        app(GrantManager::class)->propose(
            subject: $link,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true, 'fields' => ['name']]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Allow,
            source: GrantSource::Direct,
            author: $this->makeAuthor(),
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_masked_effect_with_fields_is_accepted_at_proposal(): void
    {
        $capability = $this->makeCapability('sample.read');
        $link = $this->activeLinkFor($this->makeUser('portee-effet-3@example.com'));
        $policy = $this->makePolicy();

        $grant = app(GrantManager::class)->propose(
            subject: $link,
            capability: $capability,
            policy: $policy,
            scope: ScopePayload::fromArray(['self' => true, 'fields' => ['name']]),
            conditions: ConditionsPayload::fromArray([]),
            effect: GrantEffect::Masked,
            source: GrantSource::Direct,
            author: $this->makeAuthor(),
            purpose: null,
            roleTemplate: null,
            sourceReference: null,
            validFrom: now(),
            validUntil: null,
            correlationId: (string) Str::uuid(),
        );

        $this->assertNotNull($grant);
    }
}
