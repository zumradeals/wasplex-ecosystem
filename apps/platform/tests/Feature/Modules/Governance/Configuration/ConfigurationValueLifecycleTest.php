<?php

namespace Tests\Feature\Modules\Governance\Configuration;

use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Enums\ValueVersionState;
use App\Modules\Governance\Configuration\Services\ConfigurationResolver;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Governance\Configuration\Services\Exceptions\InvalidConfigurationValueException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Cycle complet d'une ValueVersion (ADR-0002 §4, §8) : propose → soumission
 * en revue → approbation(s) → activation, avec remplacement de l'ancienne
 * version active. Niveau C1 exige deux approbateurs distincts (§3.2), C2/C3
 * un seul (§3.3, §3.4, `ConfigurationLevel::requiredApprovals()`).
 */
class ConfigurationValueLifecycleTest extends ConfigurationTestCase
{
    use RefreshDatabase;

    private function manager(): ConfigurationValueManager
    {
        return app(ConfigurationValueManager::class);
    }

    public function test_a_c2_definition_activates_after_a_single_distinct_approval(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C2, constraints: ['minimum' => 0, 'maximum' => 1000]);
        $author = $this->makeLink('author-c2@example.com');
        $approver = $this->makeLink('approver-c2@example.com');

        $version = $this->manager()->propose($definition, 42, 'Valeur de test C2.', $author);
        $this->assertSame(ValueVersionState::Draft, $version->state);

        $version = $this->manager()->submitForReview($version);
        $this->assertSame(ValueVersionState::InReview, $version->state);

        $version = $this->manager()->approve($version, $approver, 'Conforme.');
        $this->assertSame(ValueVersionState::Approved, $version->state);

        $version = $this->manager()->activate($version, $approver, (string) Str::uuid());
        $this->assertSame(ValueVersionState::Active, $version->state);
        $this->assertSame(42, $version->value);

        $this->assertSame(42, app(ConfigurationResolver::class)->value($definition->stable_key));
    }

    public function test_a_c1_definition_requires_two_distinct_approvals_before_it_can_be_activated(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C1, constraints: ['minimum' => 0]);
        $author = $this->makeLink('author-c1@example.com');
        $firstApprover = $this->makeLink('approver-c1-first@example.com');
        $secondApprover = $this->makeLink('approver-c1-second@example.com');

        $version = $this->manager()->propose($definition, 500, 'Valeur de test C1.', $author);
        $version = $this->manager()->submitForReview($version);

        $version = $this->manager()->approve($version, $firstApprover);
        $this->assertSame(ValueVersionState::InReview, $version->fresh()->state);

        $version = $this->manager()->approve($version, $secondApprover);
        $this->assertSame(ValueVersionState::Approved, $version->fresh()->state);

        $version = $this->manager()->activate($version, $firstApprover, (string) Str::uuid());
        $this->assertSame(ValueVersionState::Active, $version->state);
    }

    public function test_activating_a_new_version_replaces_the_previously_active_one(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C2);
        $author = $this->makeLink('author-replace@example.com');
        $approver = $this->makeLink('approver-replace@example.com');

        $first = $this->manager()->propose($definition, 10, 'Première valeur.', $author);
        $first = $this->manager()->submitForReview($first);
        $first = $this->manager()->approve($first, $approver);
        $first = $this->manager()->activate($first, $approver, (string) Str::uuid());

        $second = $this->manager()->propose($definition, 20, 'Seconde valeur.', $author);
        $second = $this->manager()->submitForReview($second);
        $second = $this->manager()->approve($second, $approver);
        $second = $this->manager()->activate($second, $approver, (string) Str::uuid());

        $this->assertSame(ValueVersionState::Replaced, $first->fresh()->state);
        $this->assertNotNull($first->fresh()->replaced_at);
        $this->assertSame(ValueVersionState::Active, $second->fresh()->state);

        $activation = $second->activation()->firstOrFail();
        $this->assertSame($first->id, $activation->replaced_value_version_id);

        $this->assertSame(20, app(ConfigurationResolver::class)->value($definition->stable_key));
    }

    public function test_a_single_rejection_blocks_the_version_without_erasing_prior_approvals(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C1);
        $author = $this->makeLink('author-reject@example.com');
        $firstApprover = $this->makeLink('approver-reject-first@example.com');
        $secondApprover = $this->makeLink('approver-reject-second@example.com');

        $version = $this->manager()->propose($definition, 7, 'Valeur contestée.', $author);
        $version = $this->manager()->submitForReview($version);
        $version = $this->manager()->approve($version, $firstApprover);

        $version = $this->manager()->reject($version, $secondApprover, 'Impact non simulé.');

        $this->assertSame(ValueVersionState::Rejected, $version->state);
        $this->assertSame('Impact non simulé.', $version->rejection_reason);
        $this->assertSame(1, $version->approvals()->where('decision', 'approved')->count());
    }

    public function test_a_definition_of_type_string_rejects_a_non_string_value(): void
    {
        $definition = $this->makeDefinition(valueType: ValueType::String, constraints: []);
        $author = $this->makeLink('author-type@example.com');

        $this->expectException(InvalidConfigurationValueException::class);

        $this->manager()->propose($definition, 123, 'Ne devrait jamais être accepté.', $author);
    }

    public function test_a_value_outside_the_declared_range_is_refused(): void
    {
        $definition = $this->makeDefinition(constraints: ['minimum' => 0, 'maximum' => 100]);
        $author = $this->makeLink('author-range@example.com');

        $this->expectException(InvalidConfigurationValueException::class);

        $this->manager()->propose($definition, 250, 'Hors plage.', $author);
    }
}
