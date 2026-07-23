<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des finalités autorisées (ADR-0004 §8). Une finalité sensible
 * n'est jamais un texte libre fourni comme seule justification.
 *
 * Historique figé localement (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'retired'];

    public function up(): void
    {
        Schema::create('governance.purpose_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('stable_key');
            $table->integer('version');
            $table->text('description');
            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['stable_key', 'version']);
        });

        $state = implode(',', array_map(fn (string $value): string => "'{$value}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE governance.purpose_definitions ADD CONSTRAINT purpose_definitions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE governance.purpose_definitions ADD CONSTRAINT purpose_definitions_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'ALTER TABLE governance.purpose_definitions ADD CONSTRAINT purpose_definitions_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            "ALTER TABLE governance.purpose_definitions ADD CONSTRAINT purpose_definitions_stable_key_format_check CHECK (stable_key ~ '^[a-z][a-z0-9_]*$')"
        );

        // Une seule version active d'une même clé.
        DB::statement(
            "CREATE UNIQUE INDEX purpose_definitions_one_active_per_key ON governance.purpose_definitions (stable_key) WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('governance.purpose_definitions');
    }
};
