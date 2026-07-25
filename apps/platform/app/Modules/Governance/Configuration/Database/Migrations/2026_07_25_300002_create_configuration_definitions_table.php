<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Definition (ADR-0002 §8) : clé stable, propriétaire (domaine métier),
 * type, unité, niveau C0-C3, schéma et documentation d'un paramètre
 * configurable.
 *
 * Niveau C0 volontairement absent des valeurs autorisées : « ces règles ne
 * figurent pas comme champs modifiables dans l'administration » (§3.1) — un
 * invariant constitutionnel n'est jamais une `Definition`, il est appliqué
 * directement par le code. Seuls C1, C2, C3 peuvent exister ici.
 *
 * Historique figé localement, sur le modèle exact de
 * `governance.capability_definitions` (P003-A.2 §3) : une nouvelle version
 * remplace la précédente sans effacer l'historique.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const LEVEL_VALUES = ['c1', 'c2', 'c3'];

    /**
     * @var list<string>
     */
    private const VALUE_TYPE_VALUES = ['integer', 'number', 'string', 'boolean'];

    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'retired'];

    public function up(): void
    {
        Schema::create('configuration.definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('stable_key');
            $table->integer('version');
            $table->string('domain');
            $table->string('level');
            $table->string('value_type');
            $table->string('unit')->nullable();
            $table->jsonb('constraints');
            $table->text('description');

            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();

            $table->timestamps();

            $table->unique(['stable_key', 'version']);
            $table->index('stable_key');
        });

        $level = implode(',', array_map(fn (string $v): string => "'{$v}'", self::LEVEL_VALUES));
        $valueType = implode(',', array_map(fn (string $v): string => "'{$v}'", self::VALUE_TYPE_VALUES));
        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE configuration.definitions ADD CONSTRAINT definitions_level_check CHECK (level IN ({$level}))"
        );

        DB::statement(
            "ALTER TABLE configuration.definitions ADD CONSTRAINT definitions_value_type_check CHECK (value_type IN ({$valueType}))"
        );

        DB::statement(
            "ALTER TABLE configuration.definitions ADD CONSTRAINT definitions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE configuration.definitions ADD CONSTRAINT definitions_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            'ALTER TABLE configuration.definitions ADD CONSTRAINT definitions_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'ALTER TABLE configuration.definitions ADD CONSTRAINT definitions_constraints_is_object_check '
            ."CHECK (jsonb_typeof(constraints) = 'object')"
        );

        // Une seule version active par clé, sur le modèle exact de
        // `capability_definitions_one_active_per_key`.
        DB::statement(
            'CREATE UNIQUE INDEX definitions_one_active_per_key ON configuration.definitions (stable_key) '
            ."WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration.definitions');
    }
};
