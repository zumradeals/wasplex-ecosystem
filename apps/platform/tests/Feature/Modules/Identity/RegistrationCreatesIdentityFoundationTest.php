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
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(0, Membership::query()->where('user_id', $user->id)->count());
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
            // attendu
        }

        $this->assertSame(0, User::query()->where('name', 'Compte invalide')->count());
        $this->assertSame(0, PersonAccountLink::query()->count());
        $this->assertSame(0, AssuranceState::query()->count());
    }
}
