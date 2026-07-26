<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use App\Modules\Governance\Authorization\Enums\GrantState;
use App\Modules\Governance\Authorization\Models\CapabilityDefinition;
use App\Modules\Governance\Authorization\Models\Grant;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
}
