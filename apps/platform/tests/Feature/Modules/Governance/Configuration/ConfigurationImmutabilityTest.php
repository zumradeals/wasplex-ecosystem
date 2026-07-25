<?php

namespace Tests\Feature\Modules\Governance\Configuration;

use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueVersionState;
use App\Modules\Governance\Configuration\Models\Approval;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Immuabilité sémantique, machine d'états et interdiction de suppression
 * physique (ADR-0002 §4, §8) — sur le modèle exact de
 * `Governance\Authorization\SemanticImmutabilityTest`.
 */
class ConfigurationImmutabilityTest extends ConfigurationTestCase
{
    use RefreshDatabase;

    private function manager(): ConfigurationValueManager
    {
        return app(ConfigurationValueManager::class);
    }

    public function test_an_active_definition_refuses_semantic_field_update(): void
    {
        $definition = $this->makeDefinition();

        $this->expectException(QueryException::class);

        $definition->forceFill(['description' => 'Modifiée après activation.'])->save();
    }

    public function test_a_definition_still_allows_the_state_transition_alone(): void
    {
        $definition = $this->makeDefinition();

        $definition->forceFill(['state' => DefinitionState::Retired])->save();

        $this->assertSame(DefinitionState::Retired, $definition->fresh()->state);
    }

    public function test_a_definition_refuses_physical_deletion(): void
    {
        $definition = $this->makeDefinition();

        $this->expectException(QueryException::class);

        DB::table('configuration.definitions')->where('id', $definition->id)->delete();
    }

    public function test_a_draft_value_version_content_is_freely_editable(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-draft-edit@example.com');
        $version = $this->manager()->propose($definition, 1, 'Brouillon initial.', $author);

        $version->forceFill(['value' => 2, 'justification' => 'Corrigé avant soumission.'])->save();

        $this->assertSame(2, $version->fresh()->value);
    }

    public function test_a_value_version_content_is_frozen_once_in_review(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-frozen@example.com');
        $version = $this->manager()->propose($definition, 1, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);

        $this->expectException(QueryException::class);

        $version->forceFill(['value' => 999])->save();
    }

    public function test_a_value_version_refuses_physical_deletion(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-delete@example.com');
        $version = $this->manager()->propose($definition, 1, 'Test.', $author);

        $this->expectException(QueryException::class);

        DB::table('configuration.value_versions')->where('id', $version->id)->delete();
    }

    public function test_a_value_version_refuses_an_invalid_state_transition(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-invalid-transition@example.com');
        $version = $this->manager()->propose($definition, 1, 'Test.', $author);

        $this->expectException(QueryException::class);

        // draft -> active directe : jamais permise, seule in_review ->
        // approved -> active suit le cycle (ADR-0002 §4, §8).
        $version->forceFill(['state' => ValueVersionState::Active])->save();
    }

    public function test_an_approval_refuses_update_once_recorded(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-approval-immutable@example.com');
        $approver = $this->makeLink('approver-approval-immutable@example.com');
        $version = $this->manager()->propose($definition, 1, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $this->manager()->approve($version, $approver);

        $approval = Approval::query()->where('value_version_id', $version->id)->sole();

        $this->expectException(QueryException::class);

        $approval->forceFill(['motif' => 'Modifié après coup.'])->save();
    }

    public function test_an_approval_refuses_physical_deletion(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-approval-delete@example.com');
        $approver = $this->makeLink('approver-approval-delete@example.com');
        $version = $this->manager()->propose($definition, 1, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $this->manager()->approve($version, $approver);

        $approval = Approval::query()->where('value_version_id', $version->id)->sole();

        $this->expectException(QueryException::class);

        DB::table('configuration.approvals')->where('id', $approval->id)->delete();
    }

    public function test_an_activation_refuses_physical_deletion(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-activation-delete@example.com');
        $approver = $this->makeLink('approver-activation-delete@example.com');
        $version = $this->manager()->propose($definition, 1, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $version = $this->manager()->approve($version, $approver);
        $version = $this->manager()->activate($version, $approver, (string) Str::uuid());

        $activation = $version->activation()->firstOrFail();

        $this->expectException(QueryException::class);

        DB::table('configuration.activations')->where('id', $activation->id)->delete();
    }
}
