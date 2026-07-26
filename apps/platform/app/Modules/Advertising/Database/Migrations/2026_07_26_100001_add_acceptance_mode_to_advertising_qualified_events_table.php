<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trace requêtable de la décision d'acceptation d'un QualifiedEvent
 * (arbitrage Koné/SIRR 2026-07-26) : `acceptance_mode` distingue
 * l'acceptation automatique serveur (contrôles déterministes passés) de la
 * décision humaine (`event.accept`), et une acceptation automatique épingle
 * la version exacte de la configuration des règles appliquées
 * (`acceptance_rules_configuration_key`/`_version`) — un audit futur
 * reconstruit « accepté automatiquement selon quelles règles » sans
 * ambiguïté, par simple requête, jamais via un log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising.qualified_events', function (Blueprint $table): void {
            $table->string('acceptance_mode')->nullable();
            $table->string('acceptance_rules_configuration_key')->nullable();
            $table->unsignedInteger('acceptance_rules_configuration_version')->nullable();
        });

        // Toute acceptation antérieure à cette migration passait
        // nécessairement par la route personnel (`event.accept`) : le
        // rattrapage `manual` est un fait historique, pas une hypothèse.
        DB::table('advertising.qualified_events')
            ->where('billing_status', 'accepted')
            ->update(['acceptance_mode' => 'manual']);

        DB::statement(
            "ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_acceptance_mode_check CHECK (acceptance_mode IN ('automatic', 'manual'))"
        );

        // Un événement accepté porte toujours son mode d'acceptation ; un
        // événement pending ou rejeté n'en a jamais.
        DB::statement(
            "ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_accepted_requires_acceptance_mode_check CHECK ((billing_status = 'accepted') = (acceptance_mode IS NOT NULL))"
        );

        // Une acceptation automatique sans référence de règles serait
        // inauditables : interdit au niveau du schéma.
        DB::statement(
            "ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_automatic_requires_rules_reference_check CHECK (acceptance_mode IS DISTINCT FROM 'automatic' OR (acceptance_rules_configuration_key IS NOT NULL AND acceptance_rules_configuration_version IS NOT NULL))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE advertising.qualified_events DROP CONSTRAINT IF EXISTS qualified_events_automatic_requires_rules_reference_check');
        DB::statement('ALTER TABLE advertising.qualified_events DROP CONSTRAINT IF EXISTS qualified_events_accepted_requires_acceptance_mode_check');
        DB::statement('ALTER TABLE advertising.qualified_events DROP CONSTRAINT IF EXISTS qualified_events_acceptance_mode_check');

        Schema::table('advertising.qualified_events', function (Blueprint $table): void {
            $table->dropColumn(['acceptance_mode', 'acceptance_rules_configuration_key', 'acceptance_rules_configuration_version']);
        });
    }
};
