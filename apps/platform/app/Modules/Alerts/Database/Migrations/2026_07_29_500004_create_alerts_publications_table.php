<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerts.publications` (P008-A, ecosystem/alertes/02 §5) : projection
 * publique minimisée d'un dossier `community`, distincte du dossier source.
 * Ne recopie jamais position exacte, téléphone, document brut, données
 * médicales, témoins ou preuves de propriété (voir la liste explicite de la
 * mission P008-A §7.2).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATUS_VALUES = ['draft', 'published', 'withdrawn'];

    public function up(): void
    {
        Schema::create('alerts.publications', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('case_id')
                ->constrained('alerts.cases')
                ->restrictOnDelete();

            $table->integer('version');

            $table->string('title', 200);
            $table->text('summary');
            $table->string('approximate_zone')->nullable();
            $table->jsonb('allowed_fields');

            $table->string('status')->default('draft');

            $table->foreignUuid('validated_by_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->string('withdrawal_reason', 500)->nullable();

            $table->timestamps();

            $table->unique(['case_id', 'version']);
            $table->index(['status', 'published_at']);
        });

        $status = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATUS_VALUES));

        DB::statement("ALTER TABLE alerts.publications ADD CONSTRAINT publications_status_check CHECK (status IN ({$status}))");
        DB::statement(
            'ALTER TABLE alerts.publications ADD CONSTRAINT publications_allowed_fields_is_object_check '
            ."CHECK (jsonb_typeof(allowed_fields) = 'object')"
        );

        // Une seule publication `published` à la fois par dossier — une
        // nouvelle version republiée retire d'abord l'ancienne (jamais deux
        // projections publiques actives simultanément pour le même dossier).
        DB::statement(
            'CREATE UNIQUE INDEX publications_one_active_per_case ON alerts.publications (case_id) '
            ."WHERE status = 'published'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts.publications');
    }
};
