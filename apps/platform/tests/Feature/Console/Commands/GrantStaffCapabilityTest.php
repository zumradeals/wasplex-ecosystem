<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Bootstrap manuel des toutes premières habilitations personnel Wasplex
 * (P0, demande Koné 2026-07-26) — voir GrantStaffCapability. N'invente
 * aucun mécanisme d'autorisation : ce test vérifie seulement que la
 * commande compose correctement GrantManager::propose()+activate() déjà
 * testés ailleurs (CampaignFundingRouteTest, etc.), pas les règles
 * elles-mêmes.
 */
class GrantStaffCapabilityTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function email(string $prefix): string
    {
        return $prefix.'-'.Str::uuid().'@example.com';
    }

    public function test_it_activates_a_grant_for_three_distinct_accounts(): void
    {
        $subjectEmail = $this->email('staff-subject');
        $authorEmail = $this->email('staff-author');
        $approverEmail = $this->email('staff-approver');

        $this->makeUser($subjectEmail, LinkOrigin::Migration);
        $this->makeUser($authorEmail, LinkOrigin::Migration);
        $this->makeUser($approverEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'campaign.fund',
                'subject-email' => $subjectEmail,
                'author-email' => $authorEmail,
                'approver-email' => $approverEmail,
            ],
        )->assertExitCode(0);

        $capability = CapabilityDefinition::query()->where('stable_key', 'campaign.fund')->firstOrFail();
        $subjectLink = $this->activeLinkFor(User::query()->where('email', $subjectEmail)->firstOrFail());

        $this->assertTrue(
            Grant::query()
                ->where('capability_definition_id', $capability->id)
                ->where('person_account_link_id', $subjectLink->id)
                ->where('state', GrantState::Active->value)
                ->exists(),
        );
    }

    public function test_it_refuses_when_subject_and_approver_are_the_same_account(): void
    {
        $sharedEmail = $this->email('shared');
        $authorEmail = $this->email('author-only');

        $this->makeUser($sharedEmail, LinkOrigin::Migration);
        $this->makeUser($authorEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'campaign.fund',
                'subject-email' => $sharedEmail,
                'author-email' => $authorEmail,
                'approver-email' => $sharedEmail,
            ],
        )->assertExitCode(1);

        $capability = CapabilityDefinition::query()->where('stable_key', 'campaign.fund')->firstOrFail();

        $this->assertSame(0, Grant::query()->where('capability_definition_id', $capability->id)->count());
    }

    public function test_it_refuses_an_unknown_capability(): void
    {
        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'not_a_real_capability',
                'subject-email' => $this->email('subject'),
                'author-email' => $this->email('author'),
                'approver-email' => $this->email('approver'),
            ],
        )->assertExitCode(1);
    }

    /**
     * Écart comblé 2026-07-30 : `access.view`, `configuration.view`,
     * `alert_case.review`, `alert_case.publish`, `alert_match.validate` et
     * `alert_return.verify` ont été déclarées par des lots ultérieurs
     * (P008-A, portail Configurations/Accès) sans jamais être ajoutées à
     * cet outil de bootstrap — sans quoi aucun compte réel, y compris
     * celui du fondateur, ne pouvait jamais recevoir ces capacités en
     * production.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function newlyBootstrappableCapabilities(): iterable
    {
        yield 'access.view' => ['access.view', 'governance.grant'];
        yield 'configuration.view' => ['configuration.view', 'governance.configuration_definition'];
        yield 'alert_case.review' => ['alert_case.review', 'alerts.case_category'];
        yield 'alert_case.publish' => ['alert_case.publish', 'alerts.case_category'];
        yield 'alert_match.validate' => ['alert_match.validate', 'alerts.case_category'];
        yield 'alert_return.verify' => ['alert_return.verify', 'alerts.case_category'];
        yield 'governance.system_administrator' => ['governance.system_administrator', 'governance.system'];
        yield 'wallet_deposit.review' => ['wallet_deposit.review', 'wallet.deposit'];
    }

    #[DataProvider('newlyBootstrappableCapabilities')]
    public function test_it_activates_a_grant_for_each_newly_bootstrappable_capability(string $capability, string $expectedResourceType): void
    {
        $subjectEmail = $this->email('staff-subject');
        $authorEmail = $this->email('staff-author');
        $approverEmail = $this->email('staff-approver');

        $this->makeUser($subjectEmail, LinkOrigin::Migration);
        $this->makeUser($authorEmail, LinkOrigin::Migration);
        $this->makeUser($approverEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => $capability,
                'subject-email' => $subjectEmail,
                'author-email' => $authorEmail,
                'approver-email' => $approverEmail,
            ],
        )->assertExitCode(0);

        $capabilityDefinition = CapabilityDefinition::query()->where('stable_key', $capability)->firstOrFail();
        $subjectLink = $this->activeLinkFor(User::query()->where('email', $subjectEmail)->firstOrFail());

        $grant = Grant::query()
            ->where('capability_definition_id', $capabilityDefinition->id)
            ->where('person_account_link_id', $subjectLink->id)
            ->where('state', GrantState::Active->value)
            ->firstOrFail();

        $this->assertSame($expectedResourceType, $grant->scope()->resourceType);
        $this->assertNull($grant->scope()->resourceIds);
    }

    /**
     * Addendum ADR-0004 2026-07-30 (« Auto-amorçage de l'Administrateur
     * Système ») : quand aucun compte ne détient encore
     * `governance.system_administrator`, un seul compte réel peut se
     * désigner lui-même sujet, auteur et approbateur du même octroi — la
     * garde CLI de distinction (TD-0001-A) ne s'applique plus à ce seul cas.
     */
    public function test_a_single_account_can_self_bootstrap_governance_system_administrator(): void
    {
        $founderEmail = $this->email('founder');
        $this->makeUser($founderEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'governance.system_administrator',
                'subject-email' => $founderEmail,
                'author-email' => $founderEmail,
                'approver-email' => $founderEmail,
            ],
        )->assertExitCode(0);

        $capability = CapabilityDefinition::query()->where('stable_key', 'governance.system_administrator')->firstOrFail();
        $founderLink = $this->activeLinkFor(User::query()->where('email', $founderEmail)->firstOrFail());

        $this->assertTrue(
            Grant::query()
                ->where('capability_definition_id', $capability->id)
                ->where('person_account_link_id', $founderLink->id)
                ->where('state', GrantState::Active->value)
                ->exists(),
        );
    }

    /**
     * Une fois auto-amorcé, le même compte peut s'accorder seul n'importe
     * quelle autre capacité déclarée (exemption déjà couverte par
     * `GrantManager` — ce test vérifie que la garde CLI ne la bloque plus).
     */
    public function test_an_active_system_administrator_can_self_grant_another_capability_alone(): void
    {
        $adminEmail = $this->email('sysadmin');
        $this->makeUser($adminEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'governance.system_administrator',
                'subject-email' => $adminEmail,
                'author-email' => $adminEmail,
                'approver-email' => $adminEmail,
            ],
        )->assertExitCode(0);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'campaign.fund',
                'subject-email' => $adminEmail,
                'author-email' => $adminEmail,
                'approver-email' => $adminEmail,
            ],
        )->assertExitCode(0);

        $capability = CapabilityDefinition::query()->where('stable_key', 'campaign.fund')->firstOrFail();
        $adminLink = $this->activeLinkFor(User::query()->where('email', $adminEmail)->firstOrFail());

        $this->assertTrue(
            Grant::query()
                ->where('capability_definition_id', $capability->id)
                ->where('person_account_link_id', $adminLink->id)
                ->where('state', GrantState::Active->value)
                ->exists(),
        );
    }

    public function test_bootstrapping_a_second_system_administrator_while_one_is_active_is_refused(): void
    {
        $firstEmail = $this->email('first-sysadmin');
        $this->makeUser($firstEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'governance.system_administrator',
                'subject-email' => $firstEmail,
                'author-email' => $firstEmail,
                'approver-email' => $firstEmail,
            ],
        )->assertExitCode(0);

        $secondEmail = $this->email('second-sysadmin');
        $this->makeUser($secondEmail, LinkOrigin::Migration);

        $this->artisan(
            'governance:grant-staff-capability',
            [
                'capability' => 'governance.system_administrator',
                'subject-email' => $secondEmail,
                'author-email' => $secondEmail,
                'approver-email' => $secondEmail,
            ],
        )->assertExitCode(1);
    }
}
