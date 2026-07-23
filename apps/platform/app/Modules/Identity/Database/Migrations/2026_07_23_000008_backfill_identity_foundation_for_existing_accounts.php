<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fondation Identité additive pour les comptes existants (P003-A §8).
 *
 * Traite indépendamment la liaison active personne-compte et l'état
 * d'assurance : un compte qui possède déjà une liaison mais dont l'assurance
 * a été perdue (état partiel) est réparé sans recréer de personne ni de
 * liaison surnuméraire (revue SIRR P003-A.2 §2).
 *
 * N'utilise pas insertOrIgnore : une violation inattendue doit échouer
 * explicitement et provoquer le rollback de la migration plutôt que d'être
 * masquée. La transaction de migration (Postgres) garantit qu'aucune
 * personne orpheline ne peut subsister si l'insertion de sa liaison échoue.
 *
 * Historique figé localement, sans référence aux enums applicatifs ni au
 * modèle Eloquent App\Models\User (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->orderBy('id')
            ->select('id', 'email_verified_at')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    $this->repairAccount($user->id, $user->email_verified_at !== null);
                }
            });
    }

    public function down(): void
    {
        // Migration additive et de reprise : aucune suppression de données
        // existantes n'est effectuée par la migration inverse (ADR-0006 §11).
    }

    private function repairAccount(int $userId, bool $emailVerified): void
    {
        $now = now();

        $activeLink = DB::table('identity.person_account_links')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first(['id']);

        if ($activeLink === null) {
            $personId = (string) Str::uuid7();

            DB::table('identity.people')->insert([
                'id' => $personId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('identity.person_account_links')->insert([
                'id' => (string) Str::uuid7(),
                'person_id' => $personId,
                'user_id' => $userId,
                'status' => 'active',
                'origin' => 'migration',
                'effective_from' => $now,
                'effective_to' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $hasAssurance = DB::table('identity.assurance_states')
            ->where('user_id', $userId)
            ->exists();

        if (! $hasAssurance) {
            DB::table('identity.assurance_states')->insert([
                'id' => (string) Str::uuid7(),
                'user_id' => $userId,
                'account_state' => 'active',
                'contact_assurance' => $emailVerified ? 'confirmed' : 'unconfirmed',
                'identity_assurance' => 'undeclared',
                'uniqueness_assurance' => 'unknown',
                'organization_status' => 'none',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
