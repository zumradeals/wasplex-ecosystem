<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matrice de classification des secteurs
 * (`01-classification-secteurs-et-contenus.md` §4 : « pays, secteur,
 * statut, âge minimal, justificatifs, avertissements, formats, ciblages
 * autorisés, fréquence, niveau de revue, date d'effet et approbateurs »).
 * Configuration versionnée (ADR-0002), jamais codée en dur (ADR-0010 §3) —
 * une nouvelle version ne réécrit jamais l'historique (§4 : « traçable et
 * réversible sans réécrire l'historique »).
 *
 * Historique figé localement, sur le modèle de
 * `governance.capability_definitions` (P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const SECTOR_CLASS_VALUES = ['forbidden', 'enhanced_authorization', 'standard_authorization', 'institutional_information'];

    /**
     * @var list<string>
     */
    private const REVIEW_LEVEL_VALUES = ['standard', 'enhanced'];

    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'retired'];

    public function up(): void
    {
        Schema::create('advertising.sector_classifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('country_code', 2);
            $table->string('sector');
            $table->integer('version');

            $table->string('sector_class');
            $table->unsignedInteger('minimum_age')->nullable();
            $table->jsonb('required_evidence');
            $table->jsonb('warnings');
            $table->jsonb('allowed_formats');
            $table->jsonb('allowed_targeting');
            $table->jsonb('frequency_rules');
            $table->string('review_level');
            $table->unsignedInteger('minimum_approvals');

            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();

            $table->timestamps();

            $table->unique(['country_code', 'sector', 'version']);
            $table->index(['country_code', 'sector']);
        });

        $sectorClass = implode(',', array_map(fn (string $v): string => "'{$v}'", self::SECTOR_CLASS_VALUES));
        $reviewLevel = implode(',', array_map(fn (string $v): string => "'{$v}'", self::REVIEW_LEVEL_VALUES));
        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_country_code_format_check CHECK (country_code ~ '^[A-Z]{2}$')"
        );

        DB::statement(
            "ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_sector_class_check CHECK (sector_class IN ({$sectorClass}))"
        );

        DB::statement(
            "ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_review_level_check CHECK (review_level IN ({$reviewLevel}))"
        );

        DB::statement(
            "ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            'ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_minimum_approvals_check CHECK (minimum_approvals >= 1)'
        );

        DB::statement(
            'ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        foreach (['required_evidence', 'warnings', 'allowed_formats', 'allowed_targeting'] as $column) {
            DB::statement(
                "ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_{$column}_is_array_check "
                ."CHECK (jsonb_typeof({$column}) = 'array')"
            );
        }

        DB::statement(
            'ALTER TABLE advertising.sector_classifications ADD CONSTRAINT sector_classifications_frequency_rules_is_object_check '
            ."CHECK (jsonb_typeof(frequency_rules) = 'object')"
        );

        // Une seule version active par (pays, secteur), sur le modèle de
        // capability_definitions_one_active_per_key.
        DB::statement(
            'CREATE UNIQUE INDEX sector_classifications_one_active_per_key ON advertising.sector_classifications (country_code, sector) '
            ."WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.sector_classifications');
    }
};
