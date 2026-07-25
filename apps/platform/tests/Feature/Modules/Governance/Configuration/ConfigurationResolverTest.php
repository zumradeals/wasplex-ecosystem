<?php

namespace Tests\Feature\Modules\Governance\Configuration;

use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Services\ConfigurationResolver;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Governance\Configuration\Services\Exceptions\NoActiveConfigurationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Résolution en échec fermé (ADR-0002 §5, §7.3) : jamais de valeur par
 * défaut silencieuse, même discipline que
 * `AudienceSegmentGuard::activeThreshold()`.
 */
class ConfigurationResolverTest extends ConfigurationTestCase
{
    use RefreshDatabase;

    private function resolver(): ConfigurationResolver
    {
        return app(ConfigurationResolver::class);
    }

    public function test_resolving_an_unknown_key_fails_closed(): void
    {
        $this->expectException(NoActiveConfigurationException::class);

        $this->resolver()->resolve('unknown.never_declared');
    }

    public function test_resolving_a_definition_with_no_active_value_yet_fails_closed(): void
    {
        $this->makeDefinition(stableKey: 'sample.no_active_value_yet');

        $this->expectException(NoActiveConfigurationException::class);

        $this->resolver()->resolve('sample.no_active_value_yet');
    }

    public function test_resolving_a_retired_definition_fails_closed_even_with_a_formerly_active_value(): void
    {
        $definition = $this->makeDefinition(stableKey: 'sample.later_retired');
        $author = $this->makeLink('author-retired@example.com');
        $approver = $this->makeLink('approver-retired@example.com');

        $manager = app(ConfigurationValueManager::class);
        $version = $manager->propose($definition, 5, 'Test.', $author);
        $version = $manager->submitForReview($version);
        $version = $manager->approve($version, $approver);
        $manager->activate($version, $approver, (string) Str::uuid());

        $definition->forceFill(['state' => DefinitionState::Retired])->save();

        $this->expectException(NoActiveConfigurationException::class);

        $this->resolver()->resolve('sample.later_retired');
    }

    public function test_resolve_exact_version_keeps_resolving_a_replaced_version_pinned_by_number(): void
    {
        $definition = $this->makeDefinition(stableKey: 'sample.pinned_after_replacement');
        $author = $this->makeLink('author-pinned@example.com');
        $approver = $this->makeLink('approver-pinned@example.com');
        $manager = app(ConfigurationValueManager::class);

        $first = $manager->propose($definition, 10, 'Première.', $author);
        $first = $manager->submitForReview($first);
        $first = $manager->approve($first, $approver);
        $first = $manager->activate($first, $approver, (string) Str::uuid());

        $second = $manager->propose($definition, 20, 'Seconde.', $author);
        $second = $manager->submitForReview($second);
        $second = $manager->approve($second, $approver);
        $manager->activate($second, $approver, (string) Str::uuid());

        $pinned = $this->resolver()->resolveExactVersion('sample.pinned_after_replacement', $first->version);

        $this->assertSame(10, $pinned->value);
        $this->assertSame('replaced', $pinned->state->value);
    }

    public function test_resolve_exact_version_fails_closed_for_a_version_never_activated(): void
    {
        $definition = $this->makeDefinition(stableKey: 'sample.never_activated');
        $author = $this->makeLink('author-never@example.com');
        $manager = app(ConfigurationValueManager::class);

        $version = $manager->propose($definition, 10, 'Jamais activée.', $author);

        $this->expectException(NoActiveConfigurationException::class);

        $this->resolver()->resolveExactVersion('sample.never_activated', $version->version);
    }

    public function test_resolve_exact_version_fails_closed_for_an_unknown_key(): void
    {
        $this->expectException(NoActiveConfigurationException::class);

        $this->resolver()->resolveExactVersion('sample.unknown_pricing_key', 1);
    }
}
