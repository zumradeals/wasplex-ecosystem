<?php

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Enums\LinkStatus;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_contradictory_active_links_for_the_same_account_are_refused(): void
    {
        $user = app(RegistersUserIdentity::class)->register([
            'name' => 'Deux liaisons',
            'email' => 'deux-liaisons@example.com',
            'password' => 'password',
        ]);

        $otherPerson = Person::create();

        $this->expectException(QueryException::class);

        DB::table('identity.person_account_links')->insert([
            'id' => (string) Str::uuid7(),
            'person_id' => $otherPerson->id,
            'user_id' => $user->id,
            'status' => LinkStatus::Active->value,
            'origin' => LinkOrigin::SupportReview->value,
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_account_state_is_rejected_by_postgres(): void
    {
        $user = app(RegistersUserIdentity::class)->register([
            'name' => 'État invalide',
            'email' => 'etat-invalide@example.com',
            'password' => 'password',
        ]);

        DB::table('identity.assurance_states')->where('user_id', $user->id)->delete();

        $this->expectException(QueryException::class);

        DB::table('identity.assurance_states')->insert([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'account_state' => 'not_a_real_state',
            'contact_assurance' => 'unconfirmed',
            'identity_assurance' => 'undeclared',
            'uniqueness_assurance' => 'unknown',
            'organization_status' => 'none',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_invalid_organization_category_is_rejected_by_postgres(): void
    {
        $this->expectException(QueryException::class);

        DB::table('identity.organizations')->insert([
            'id' => (string) Str::uuid7(),
            'category' => 'agent',
            'legal_name' => 'Organisation invalide',
            'display_name' => 'Organisation invalide',
            'country_code' => 'CI',
            'state' => 'draft',
            'effective_from' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_no_global_identity_score_exists(): void
    {
        $suspiciousFragments = ['score', 'trust_level', 'rating'];

        foreach (['identity.people', 'identity.assurance_states', 'identity.organizations', 'identity.memberships'] as $table) {
            $columns = Schema::getColumnListing($table);

            foreach ($columns as $column) {
                foreach ($suspiciousFragments as $fragment) {
                    $this->assertStringNotContainsString(
                        $fragment,
                        strtolower($column),
                        "La colonne {$table}.{$column} suggère un score global, interdit par la Constitution (article 8)."
                    );
                }
            }
        }
    }
}
