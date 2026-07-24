<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ModerationCase (ADR-0010 §3 ; `02-preuves-moderation-et-destinations.md`
 * §1, §4 ; `03-signalements-sanctions-et-remuneration.md` §1-2) : campagne
 * concernée, motif, gravité, décision, mesure conservatoire, recours. Un
 * signalement n'est jamais à lui seul une preuve de violation (§1) : ce
 * dossier n'entraîne aucune écriture Ledger directe — seul le blocage
 * applicatif de nouvelles réservations (`Campaign.state = suspended`) en
 * découle (ADR-0010 §4, dernière ligne du tableau).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATUS_VALUES = ['open', 'resolved'];

    /**
     * @var list<string>
     */
    private const PRECAUTIONARY_MEASURE_VALUES = ['none', 'limited_diffusion', 'campaign_suspended', 'destination_blocked', 'advertiser_blocked'];

    public function up(): void
    {
        Schema::create('advertising.moderation_cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_id')
                ->constrained('advertising.campaigns')
                ->restrictOnDelete();
            $table->foreignUuid('campaign_version_id')
                ->nullable()
                ->constrained('advertising.campaign_versions')
                ->restrictOnDelete();

            $table->text('reason');
            $table->string('observed_destination')->nullable();
            $table->string('severity');

            $table->string('status');
            $table->string('precautionary_measure');
            $table->text('decision')->nullable();
            $table->string('recourse_status')->nullable();

            $table->timestamps();

            $table->index('campaign_id');
            $table->index('status');
        });

        $status = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATUS_VALUES));
        $measure = implode(',', array_map(fn (string $v): string => "'{$v}'", self::PRECAUTIONARY_MEASURE_VALUES));

        DB::statement(
            "ALTER TABLE advertising.moderation_cases ADD CONSTRAINT moderation_cases_status_check CHECK (status IN ({$status}))"
        );

        DB::statement(
            "ALTER TABLE advertising.moderation_cases ADD CONSTRAINT moderation_cases_precautionary_measure_check CHECK (precautionary_measure IN ({$measure}))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.moderation_cases');
    }
};
