<?php

namespace Tests\Feature\Modules\Governance\Configuration;

use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\ValueVersionState;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Governance\Configuration\Services\Exceptions\DuplicateApprovalException;
use App\Modules\Governance\Configuration\Services\Exceptions\InsufficientApprovalsException;
use App\Modules\Governance\Configuration\Services\Exceptions\SelfApprovalRefusedException;
use App\Modules\Governance\Configuration\Services\Exceptions\ValueVersionNotInStateException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aucun acteur n'est jamais l'unique contrôleur de sa propre configuration
 * (ADR-0002 §3.2, §3.3 ; même discipline que
 * `GrantManager`/`CampaignVersionService`). Vérifié à la fois côté service
 * et par déclencheur PL/pgSQL, en défense en profondeur.
 */
class ConfigurationSeparationOfDutiesTest extends ConfigurationTestCase
{
    use RefreshDatabase;

    private function manager(): ConfigurationValueManager
    {
        return app(ConfigurationValueManager::class);
    }

    public function test_the_author_cannot_approve_its_own_proposal(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-self-approve@example.com');

        $version = $this->manager()->propose($definition, 10, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);

        $this->expectException(SelfApprovalRefusedException::class);

        $this->manager()->approve($version, $author);
    }

    public function test_the_author_cannot_activate_its_own_proposal_even_when_approved(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C2);
        $author = $this->makeLink('author-self-activate@example.com');
        $approver = $this->makeLink('approver-self-activate@example.com');

        $version = $this->manager()->propose($definition, 10, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $version = $this->manager()->approve($version, $approver);

        $this->expectException(SelfApprovalRefusedException::class);

        $this->manager()->activate($version, $author, (string) Str::uuid());
    }

    public function test_an_approver_cannot_decide_twice_for_the_same_version(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C1);
        $author = $this->makeLink('author-dup@example.com');
        $approver = $this->makeLink('approver-dup@example.com');

        $version = $this->manager()->propose($definition, 10, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $version = $this->manager()->approve($version, $approver);

        $this->expectException(DuplicateApprovalException::class);

        $this->manager()->approve($version, $approver);
    }

    public function test_a_c1_definition_cannot_be_activated_with_only_one_approval(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C1);
        $author = $this->makeLink('author-insufficient@example.com');
        $approver = $this->makeLink('approver-insufficient@example.com');

        $version = $this->manager()->propose($definition, 10, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $version = $this->manager()->approve($version, $approver);

        // Toujours in_review, faute du second approbateur requis par le
        // niveau C1 : activate() doit refuser une version qui n'a jamais
        // atteint approved.
        $this->expectException(ValueVersionNotInStateException::class);

        $this->manager()->activate($version, $approver, (string) Str::uuid());
    }

    /**
     * Défense en profondeur au niveau service : si l'état `approved` était
     * atteint sans passer par {@see ConfigurationValueManager::approve()}
     * (contournement direct, jamais permis par le service lui-même),
     * `activate()` recompte les approbations actives et refuse toujours.
     */
    public function test_activate_recounts_approvals_even_if_state_was_forced_to_approved(): void
    {
        $definition = $this->makeDefinition(level: ConfigurationLevel::C1);
        $author = $this->makeLink('author-forced@example.com');
        $activator = $this->makeLink('activator-forced@example.com');

        $version = $this->manager()->propose($definition, 10, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);
        $version->forceFill(['state' => ValueVersionState::Approved])->save();

        $this->expectException(InsufficientApprovalsException::class);

        $this->manager()->activate($version->fresh(), $activator, (string) Str::uuid());
    }

    /**
     * Défense en profondeur au niveau base de données : même en
     * contournant `ConfigurationValueManager` par une insertion directe,
     * le déclencheur `approvals_prevent_self_approval` refuse
     * l'auto-approbation.
     */
    public function test_database_trigger_refuses_self_approval_even_bypassing_the_service(): void
    {
        $definition = $this->makeDefinition();
        $author = $this->makeLink('author-trigger@example.com');
        $version = $this->manager()->propose($definition, 10, 'Test.', $author);
        $version = $this->manager()->submitForReview($version);

        $this->expectException(QueryException::class);

        DB::table('configuration.approvals')->insert([
            'id' => (string) Str::uuid(),
            'value_version_id' => $version->id,
            'approver_person_account_link_id' => $author->id,
            'decision' => 'approved',
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
