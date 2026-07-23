<?php

namespace Tests\Feature\Modules\Identity;

use App\Models\User;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\UniquenessAssurance;
use App\Modules\Identity\Models\AssuranceState;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class RegistrationCreatesIdentityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_account_person_link_and_assurance_atomically(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Aïcha Koné',
            'email' => 'aicha@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'aicha@example.com')->firstOrFail();

        $link = PersonAccountLink::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(LinkStatus::Active, $link->status);

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(AccountState::Active, $assurance->account_state);
        $this->assertSame(IdentityAssurance::Undeclared, $assurance->identity_assurance);
        $this->assertSame(UniquenessAssurance::Unknown, $assurance->uniqueness_assurance);
        $this->assertSame(OrganizationStatus::None, $assurance->organization_status);

        $this->assertNotNull($user->public_id);
    }

    public function test_unverified_account_starts_with_unconfirmed_contact_assurance(): void
    {
        $service = app(RegistersUserIdentity::class);

        $user = $service->register([
            'name' => 'Moussa Diarra',
            'email' => 'moussa@example.com',
            'password' => 'password',
        ]);

        $this->assertNull($user->email_verified_at);

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(ContactAssurance::Unconfirmed, $assurance->contact_assurance);
    }

    public function test_a_genuinely_verified_account_can_be_represented_as_confirmed(): void
    {
        $service = app(RegistersUserIdentity::class);

        $user = $service->register([
            'name' => 'Fatou Traoré',
            'email' => 'fatou@example.com',
            'password' => 'password',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();
        event(new Verified($user));

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(ContactAssurance::Confirmed, $assurance->contact_assurance);
    }

    public function test_created_identifiers_are_uuid_version_7(): void
    {
        $service = app(RegistersUserIdentity::class);

        $user = $service->register([
            'name' => 'Ibrahim Cissé',
            'email' => 'ibrahim@example.com',
            'password' => 'password',
        ]);

        $link = PersonAccountLink::query()->where('user_id', $user->id)->firstOrFail();
        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(7, Uuid::fromString($link->person_id)->getFields()->getVersion());
        $this->assertSame(7, Uuid::fromString($link->id)->getFields()->getVersion());
        $this->assertSame(7, Uuid::fromString($assurance->id)->getFields()->getVersion());
        $this->assertSame(7, Uuid::fromString($user->public_id)->getFields()->getVersion());
    }

    public function test_individual_user_does_not_require_an_organization(): void
    {
        $service = app(RegistersUserIdentity::class);

        $user = $service->register([
            'name' => 'Salimata Bamba',
            'email' => 'salimata@example.com',
            'password' => 'password',
        ]);

        $assurance = AssuranceState::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(OrganizationStatus::None, $assurance->organization_status);

        $membershipCount = Membership::query()
            ->whereHas('personAccountLink', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        $this->assertSame(0, $membershipCount);
    }

    public function test_registration_is_transactional_and_leaves_no_partial_account_on_failure(): void
    {
        $service = app(RegistersUserIdentity::class);

        try {
            $service->register([
                'name' => 'Compte invalide',
                'email' => null,
                'password' => 'password',
            ]);
            $this->fail('An exception was expected for a null email.');
        } catch (\Throwable) {
            // attendu : violation NOT NULL sur users.email avant même la
            // création d'une personne.
        }

        $this->assertSame(0, User::query()->where('name', 'Compte invalide')->count());
        $this->assertSame(0, PersonAccountLink::query()->count());
        $this->assertSame(0, AssuranceState::query()->count());
    }

    public function test_registration_rolls_back_the_already_inserted_user_when_a_later_step_fails(): void
    {
        // Contrainte PostgreSQL temporaire, propre à ce test, forçant l'échec
        // de toute insertion dans identity.people après que le compte a déjà
        // été inséré : démontre le rollback d'une étape intermédiaire de la
        // transaction (revue SIRR P003-A.2 §5). Rien de comparable n'existe
        // dans le code de production : la contrainte est ajoutée puis retirée
        // exclusivement depuis ce test, sur wasplex_test.
        DB::statement('ALTER TABLE identity.people ADD CONSTRAINT people_test_forced_failure CHECK (false)');

        try {
            try {
                app(RegistersUserIdentity::class)->register([
                    'name' => 'Rollback Intermédiaire',
                    'email' => 'rollback-intermediaire@example.com',
                    'password' => 'password',
                ]);

                $this->fail('A QueryException was expected when inserting into identity.people.');
            } catch (QueryException) {
                // attendu : la contrainte de test force l'échec après l'insertion du User.
            }
        } finally {
            DB::statement('ALTER TABLE identity.people DROP CONSTRAINT IF EXISTS people_test_forced_failure');
        }

        $this->assertSame(0, User::query()->where('email', 'rollback-intermediaire@example.com')->count());
        $this->assertSame(0, PersonAccountLink::query()->count());
        $this->assertSame(0, AssuranceState::query()->count());
    }
}
