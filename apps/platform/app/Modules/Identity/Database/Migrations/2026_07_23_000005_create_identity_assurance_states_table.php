<?php

use App\Modules\Identity\Enums\AccountState;
use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\UniquenessAssurance;
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
 */
return new class extends Migration
{
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

        $this->addEnumCheck('account_state', AccountState::values());
        $this->addEnumCheck('contact_assurance', ContactAssurance::values());
        $this->addEnumCheck('identity_assurance', IdentityAssurance::values());
        $this->addEnumCheck('uniqueness_assurance', UniquenessAssurance::values());
        $this->addEnumCheck('organization_status', OrganizationStatus::values());
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
