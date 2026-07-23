<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des capacités atomiques (ADR-0004 §5). Une capacité nommée ne
 * vaut jamais autorisation à elle seule.
 *
 * Historique figé localement : aucune valeur ci-dessous ne doit jamais être
 * remplacée par une référence à un enum applicatif (leçon retenue de la
 * revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const RISK_CLASS_VALUES = ['ordinary', 'sensitive', 'critical'];

    /**
     * @var list<string>
     */
    private const SESSION_ASSURANCE_VALUES = ['weak', 'standard', 'strong'];

    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'retired'];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_SEGMENTS = ['admin', 'super_admin', 'god', 'root', 'premium', 'elite', 'master', 'all', 'any'];

    public function up(): void
    {
        Schema::create('governance.capability_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('stable_key');
            $table->integer('version');
            $table->string('domain');
            $table->string('action');
            $table->text('description');
            $table->string('risk_class');
            $table->boolean('purpose_required');
            $table->boolean('approval_required');
            $table->string('minimum_session_assurance');
            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['stable_key', 'version']);
            $table->index('domain');
        });

        $riskClass = $this->quotedList(self::RISK_CLASS_VALUES);
        $sessionAssurance = $this->quotedList(self::SESSION_ASSURANCE_VALUES);
        $state = $this->quotedList(self::STATE_VALUES);
        $forbidden = $this->quotedList(self::FORBIDDEN_SEGMENTS);

        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_risk_class_check CHECK (risk_class IN ({$riskClass}))"
        );

        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_session_assurance_check CHECK (minimum_session_assurance IN ({$sessionAssurance}))"
        );

        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_positive_version_check CHECK (version > 0)'
        );

        // Clé strictement au format domaine.action, en minuscules, sans joker.
        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_stable_key_format_check CHECK (stable_key ~ '^[a-z][a-z0-9_]*\\.[a-z][a-z0-9_]*$')"
        );

        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_stable_key_no_wildcard_check CHECK (stable_key NOT LIKE '%*%')"
        );

        // Aucune clé domaine.action ne doit servir de pouvoir générique.
        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_domain_forbidden_check CHECK (split_part(stable_key, '.', 1) NOT IN ({$forbidden}))"
        );

        DB::statement(
            "ALTER TABLE governance.capability_definitions ADD CONSTRAINT capability_definitions_action_forbidden_check CHECK (split_part(stable_key, '.', 2) NOT IN ({$forbidden}))"
        );

        // Une seule version active d'une même clé.
        DB::statement(
            "CREATE UNIQUE INDEX capability_definitions_one_active_per_key ON governance.capability_definitions (stable_key) WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('governance.capability_definitions');
    }

    /**
     * @param  list<string>  $values
     */
    private function quotedList(array $values): string
    {
        return implode(',', array_map(fn (string $value): string => "'{$value}'", $values));
    }
};
