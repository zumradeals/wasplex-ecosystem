<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ValueVersion (ADR-0002 §8) : valeur immuable proposée pour une
 * `Definition`, avec sa justification et son cycle de vie.
 *
 * Cycle réduit au noyau (W2) : `draft` → `in_review` → `approved` → `active`
 * → `replaced`, plus `rejected` et `cancelled` (« annulée avant effet »).
 * Volontairement absents de ce lot : `simulated` et `scheduled` (ADR-0002 §4)
 * — aucune Simulation ni prise d'effet programmée à date future n'existe
 * encore ; une activation prend toujours effet immédiatement. Porte de
 * reprise documentée dans TD-0006-A/TD-0006-C avant tout besoin réel de
 * simulation ou de programmation.
 *
 * Historique figé localement, sur le modèle exact de
 * `advertising.campaign_versions` : une correction crée toujours une
 * nouvelle version, jamais une mutation de l'ancienne.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'in_review', 'approved', 'active', 'replaced', 'rejected', 'cancelled'];

    public function up(): void
    {
        Schema::create('configuration.value_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('definition_id')
                ->constrained('configuration.definitions')
                ->restrictOnDelete();
            $table->integer('version');

            $table->jsonb('value');
            $table->text('justification');

            $table->string('state');

            $table->foreignUuid('author_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestampTz('replaced_at')->nullable();

            $table->timestamps();

            $table->unique(['definition_id', 'version']);
            $table->index('definition_id');
            $table->index('state');
        });

        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE configuration.value_versions ADD CONSTRAINT value_versions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE configuration.value_versions ADD CONSTRAINT value_versions_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            'ALTER TABLE configuration.value_versions ADD CONSTRAINT value_versions_rejected_consistency_check '
            ."CHECK ((state = 'rejected') = (rejected_at IS NOT NULL))"
        );

        DB::statement(
            'ALTER TABLE configuration.value_versions ADD CONSTRAINT value_versions_replaced_requires_replaced_at_check '
            ."CHECK (state <> 'replaced' OR replaced_at IS NOT NULL)"
        );

        // Une seule version active par définition à la fois (ADR-0002 §5 :
        // « deux règles de même niveau ne peuvent se chevaucher sur le même
        // périmètre et la même période » — le noyau ne modélise encore
        // aucun périmètre géographique/contractuel, une seule portée
        // globale par définition).
        DB::statement(
            'CREATE UNIQUE INDEX value_versions_one_active_per_definition ON configuration.value_versions (definition_id) '
            ."WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration.value_versions');
    }
};
