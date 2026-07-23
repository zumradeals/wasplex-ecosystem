<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versions de politique (ADR-0002, ADR-0004 §10). Une décision d'autorisation
 * cite toujours la version de politique appliquée. Les versions passées
 * restent reconstructibles et ne sont jamais éditées rétroactivement.
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
        Schema::create('governance.policy_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('stable_key');
            $table->integer('version');
            $table->string('state');
            $table->string('checksum', 64);
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['stable_key', 'version']);
        });

        $state = implode(',', array_map(fn (string $value): string => "'{$value}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE governance.policy_versions ADD CONSTRAINT policy_versions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE governance.policy_versions ADD CONSTRAINT policy_versions_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        DB::statement(
            'ALTER TABLE governance.policy_versions ADD CONSTRAINT policy_versions_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            "ALTER TABLE governance.policy_versions ADD CONSTRAINT policy_versions_checksum_format_check CHECK (checksum ~ '^[a-f0-9]{64}$')"
        );

        // Une seule version active d'une même politique à un instant donné.
        DB::statement(
            "CREATE UNIQUE INDEX policy_versions_one_active_per_key ON governance.policy_versions (stable_key) WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('governance.policy_versions');
    }
};
