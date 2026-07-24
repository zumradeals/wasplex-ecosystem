<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seuil minimal de taille d'un segment d'audience (AMD-0009 §13, ADR-0010
 * §3 : « le seuil exact est une configuration versionnée sous ADR-0002,
 * jamais une constante applicative »). Une seule ligne active à la fois,
 * sur le modèle exact de `sector_classifications`.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'retired'];

    public function up(): void
    {
        Schema::create('advertising.audience_segment_size_thresholds', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->integer('version');
            $table->unsignedInteger('minimum_size');

            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();

            $table->timestamps();

            $table->unique('version');
        });

        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE advertising.audience_segment_size_thresholds ADD CONSTRAINT audience_segment_size_thresholds_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE advertising.audience_segment_size_thresholds ADD CONSTRAINT audience_segment_size_thresholds_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            'ALTER TABLE advertising.audience_segment_size_thresholds ADD CONSTRAINT audience_segment_size_thresholds_minimum_size_check CHECK (minimum_size > 0)'
        );

        DB::statement(
            'ALTER TABLE advertising.audience_segment_size_thresholds ADD CONSTRAINT audience_segment_size_thresholds_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'CREATE UNIQUE INDEX audience_segment_size_thresholds_one_active ON advertising.audience_segment_size_thresholds (state) '
            ."WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.audience_segment_size_thresholds');
    }
};
