<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerts.correspondence_reports` (P008-A, ecosystem/alertes/02 §7) : une
 * personne prétend reconnaître le bien/la personne d'un dossier publié. Le
 * moteur produit un candidat, jamais une décision finale — les
 * caractéristiques secrètes du bien ne sont jamais révélées au proposant,
 * seule sa réponse est enregistrée pour rapprochement humain.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const REVIEW_STATE_VALUES = ['pending', 'candidate', 'validated', 'rejected'];

    public function up(): void
    {
        Schema::create('alerts.correspondence_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('case_id')
                ->constrained('alerts.cases')
                ->restrictOnDelete();

            $table->foreignUuid('reporter_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->text('non_public_description');
            $table->jsonb('verification_response');

            $table->string('review_state')->default('pending');

            $table->foreignUuid('reviewed_by_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['case_id', 'review_state']);
        });

        $reviewState = implode(',', array_map(fn (string $v): string => "'{$v}'", self::REVIEW_STATE_VALUES));

        DB::statement("ALTER TABLE alerts.correspondence_reports ADD CONSTRAINT correspondence_reports_review_state_check CHECK (review_state IN ({$reviewState}))");
        DB::statement(
            'ALTER TABLE alerts.correspondence_reports ADD CONSTRAINT correspondence_reports_verification_response_is_object_check '
            ."CHECK (jsonb_typeof(verification_response) = 'object')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts.correspondence_reports');
    }
};
