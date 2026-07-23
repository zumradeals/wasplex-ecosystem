<?php

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Démontre que la migration de rattrapage (P003-A.2 §3) répare l'état
 * partiel d'un compte — liaison active existante mais assurance absente —
 * sans jamais recréer de personne ni de liaison surnuméraire.
 */
class BackfillIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_repairs_missing_assurance_without_creating_new_person_or_link(): void
    {
        $user = app(RegistersUserIdentity::class)->register([
            'name' => 'Compte à réparer',
            'email' => 'reprise@example.com',
            'password' => 'password',
        ]);

        $link = PersonAccountLink::query()->where('user_id', $user->id)->firstOrFail();

        // Simule un état partiel réel : la liaison active existe, mais son
        // assurance a été perdue (incident, reprise interrompue, etc.).
        AssuranceState::query()->where('user_id', $user->id)->delete();

        $personCountBefore = Person::query()->count();
        $linkCountBefore = PersonAccountLink::query()->count();

        $this->runBackfillMigration();

        $this->assertSame($personCountBefore, Person::query()->count());
        $this->assertSame($linkCountBefore, PersonAccountLink::query()->count());

        $this->assertTrue(
            PersonAccountLink::query()->whereKey($link->id)->where('status', 'active')->exists()
        );

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(AccountState::Active, $assurance->account_state);
        $this->assertSame(ContactAssurance::Unconfirmed, $assurance->contact_assurance);
    }

    public function test_backfill_is_idempotent_when_run_twice_on_a_complete_account(): void
    {
        app(RegistersUserIdentity::class)->register([
            'name' => 'Compte déjà complet',
            'email' => 'complet@example.com',
            'password' => 'password',
        ]);

        $personCountBefore = Person::query()->count();
        $linkCountBefore = PersonAccountLink::query()->count();
        $assuranceCountBefore = AssuranceState::query()->count();

        $this->runBackfillMigration();
        $this->runBackfillMigration();

        $this->assertSame($personCountBefore, Person::query()->count());
        $this->assertSame($linkCountBefore, PersonAccountLink::query()->count());
        $this->assertSame($assuranceCountBefore, AssuranceState::query()->count());
    }

    private function runBackfillMigration(): void
    {
        $migration = require base_path(
            'app/Modules/Identity/Database/Migrations/2026_07_23_000008_backfill_identity_foundation_for_existing_accounts.php'
        );

        $migration->up();
    }
}
