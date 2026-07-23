<?php

use App\Modules\Identity\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Appartenance nominative reliant une personne, un compte et une organisation
 * (P003-A §6). N'accorde par elle-même aucune capacité (ADR-0004 §5, §22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('identity.people')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->constrained('identity.organizations')->cascadeOnDelete();
            $table->string('status');
            $table->string('title')->nullable();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('user_id');
            $table->index('person_id');
        });

        $status = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            MembershipStatus::values(),
        ));

        DB::statement(
            "ALTER TABLE identity.memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ({$status}))"
        );

        DB::statement(
            'ALTER TABLE identity.memberships ADD CONSTRAINT memberships_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.memberships');
    }
};
