<?php

use App\Models\User;
use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\UniquenessAssurance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fondation Identité additive pour les comptes existants (P003-A §8).
 *
 * Idempotente : un compte déjà doté d'une liaison active est ignoré, et les
 * insertions utilisent ON CONFLICT DO NOTHING pour rester rejouables sans
 * doublon en cas de reprise. Aucune donnée existante n'est supprimée ni
 * modifiée en dehors de la fondation Identité elle-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->whereNotIn('id', function ($query): void {
                $query->select('user_id')
                    ->from('identity.person_account_links')
                    ->where('status', LinkStatus::Active->value);
            })
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                $now = now();

                foreach ($users as $user) {
                    $personId = (string) Str::uuid7();

                    DB::table('identity.people')->insertOrIgnore([
                        'id' => $personId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('identity.person_account_links')->insertOrIgnore([
                        'id' => (string) Str::uuid7(),
                        'person_id' => $personId,
                        'user_id' => $user->id,
                        'status' => LinkStatus::Active->value,
                        'origin' => LinkOrigin::Migration->value,
                        'effective_from' => $now,
                        'effective_to' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('identity.assurance_states')->insertOrIgnore([
                        'id' => (string) Str::uuid7(),
                        'user_id' => $user->id,
                        'account_state' => AccountState::Active->value,
                        'contact_assurance' => $user->email_verified_at !== null
                            ? ContactAssurance::Confirmed->value
                            : ContactAssurance::Unconfirmed->value,
                        'identity_assurance' => IdentityAssurance::Undeclared->value,
                        'uniqueness_assurance' => UniquenessAssurance::Unknown->value,
                        'organization_status' => OrganizationStatus::None->value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Migration additive et de reprise : aucune suppression de données
        // existantes n'est effectuée par la migration inverse (ADR-0006 §11).
    }
};
