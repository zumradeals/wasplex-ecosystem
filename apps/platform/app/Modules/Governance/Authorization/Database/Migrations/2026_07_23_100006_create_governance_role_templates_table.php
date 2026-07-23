<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rôle modèle : ensemble versionné de capacités proposées (ADR-0004 §6).
 *
 * Invariant essentiel : un rôle modèle n'autorise rien par lui-même. Aucune
 * colonne de portée, condition ou effet n'existe ici — seul un grant
 * explicitement activé produit un droit.
 *
 * Historique figé localement (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'retired'];

    /**
     * @var list<string>
     */
    private const ORGANIZATION_CATEGORY_VALUES = ['wasplex', 'advertiser', 'institution'];

    public function up(): void
    {
        Schema::create('governance.role_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('stable_key');
            $table->integer('version');
            $table->string('label');
            $table->text('description');
            $table->string('organization_category')->nullable();
            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['stable_key', 'version']);
        });

        $state = implode(',', array_map(fn (string $value): string => "'{$value}'", self::STATE_VALUES));
        $category = implode(',', array_map(fn (string $value): string => "'{$value}'", self::ORGANIZATION_CATEGORY_VALUES));

        DB::statement(
            "ALTER TABLE governance.role_templates ADD CONSTRAINT role_templates_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            "ALTER TABLE governance.role_templates ADD CONSTRAINT role_templates_organization_category_check CHECK (organization_category IS NULL OR organization_category IN ({$category}))"
        );

        DB::statement(
            'ALTER TABLE governance.role_templates ADD CONSTRAINT role_templates_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'ALTER TABLE governance.role_templates ADD CONSTRAINT role_templates_positive_version_check CHECK (version > 0)'
        );

        // Une seule version active d'une même clé.
        DB::statement(
            "CREATE UNIQUE INDEX role_templates_one_active_per_key ON governance.role_templates (stable_key) WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('governance.role_templates');
    }
};
