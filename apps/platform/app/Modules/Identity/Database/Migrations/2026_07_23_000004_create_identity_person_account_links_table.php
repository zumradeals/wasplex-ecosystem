<?php

use App\Modules\Identity\Enums\LinkOrigin;
use App\Modules\Identity\Enums\LinkStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liaison historisée entre une personne et un compte (P003-A §6, ADR-0006 §6).
 *
 * Un compte ne possède jamais simultanément plusieurs liaisons actives
 * contradictoires : garanti par un index unique partiel PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.person_account_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('identity.people')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->string('origin');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('person_id');
        });

        $status = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            LinkStatus::values(),
        ));

        $origin = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            LinkOrigin::values(),
        ));

        DB::statement(
            "ALTER TABLE identity.person_account_links ADD CONSTRAINT person_account_links_status_check CHECK (status IN ({$status}))"
        );

        DB::statement(
            "ALTER TABLE identity.person_account_links ADD CONSTRAINT person_account_links_origin_check CHECK (origin IN ({$origin}))"
        );

        DB::statement(
            'ALTER TABLE identity.person_account_links ADD CONSTRAINT person_account_links_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        // Un compte ne possède jamais simultanément deux liaisons actives.
        DB::statement(
            "CREATE UNIQUE INDEX person_account_links_one_active_per_user ON identity.person_account_links (user_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.person_account_links');
    }
};
