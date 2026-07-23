<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Axes d'assurance d'un compte, séparés et sans score global
 * (ecosystem/identite/01-niveaux-et-preuves.md ; AMD-0009 §18).
 *
 * `session_assurance` n'est volontairement pas une colonne de cette table :
 * il appartient au contexte de session, pas à un état permanent (P003-A §6).
 *
 * Historique figé localement : les valeurs ci-dessous ne doivent jamais être
 * remplacées par une référence aux enums applicatifs (AccountState,
 * ContactAssurance, IdentityAssurance, UniquenessAssurance,
 * OrganizationStatus), afin qu'une évolution future de ces enums ne modifie
 * pas le comportement de cette migration déjà exécutée (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const ENUM_VALUES = [
        'account_state' => ['invited', 'active', 'suspended', 'closed'],
        'contact_assurance' => ['unconfirmed', 'confirmed'],
        'identity_assurance' => ['undeclared', 'declared', 'verified', 'reinforced'],
        'uniqueness_assurance' => ['unknown', 'probable', 'sufficient', 'disputed'],
        'organization_status' => ['none', 'representative_pending', 'authorized', 'suspended'],
    ];

    public function up(): void
    {
        Schema::create('identity.assurance_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('account_state');
            $table->string('contact_assurance');
            $table->string('identity_assurance');
            $table->string('uniqueness_assurance');
            $table->string('organization_status');
            $table->timestamps();
        });

        foreach (self::ENUM_VALUES as $column => $values) {
            $this->addEnumCheck($column, $values);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.assurance_states');
    }

    /**
     * @param  list<string>  $values
     */
    private function addEnumCheck(string $column, array $values): void
    {
        $list = implode(',', array_map(fn (string $value): string => "'{$value}'", $values));

        DB::statement(
            "ALTER TABLE identity.assurance_states ADD CONSTRAINT assurance_states_{$column}_check CHECK ({$column} IN ({$list}))"
        );
    }
};
