<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approval (ADR-0002 §8) : décision nominative d'un approbateur sur une
 * `ValueVersion` — jamais une simple case cochée sur la version elle-même,
 * pour que le niveau C1 (« approbation par deux personnes habilitées dont
 * une indépendante de l'auteur », §3.2) soit représentable : plusieurs
 * décisions distinctes pour une même version.
 *
 * Un approbateur ne peut décider qu'une seule fois par version
 * (`approvals_one_decision_per_approver`) et ne peut jamais être l'auteur
 * de la version qu'il approuve ou refuse — vérifié ici par déclencheur
 * (table distincte de `value_versions`, contrairement à
 * `grants_author_not_approver_check` qui pouvait rester une simple
 * contrainte `CHECK` sur une unique table), en défense en profondeur de
 * `ConfigurationValueManager`.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const DECISION_VALUES = ['approved', 'rejected'];

    public function up(): void
    {
        Schema::create('configuration.approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('value_version_id')
                ->constrained('configuration.value_versions')
                ->restrictOnDelete();

            $table->foreignUuid('approver_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->string('decision');
            $table->string('motif', 500)->nullable();
            $table->timestampTz('decided_at');

            $table->timestamps();

            $table->unique(['value_version_id', 'approver_person_account_link_id'], 'approvals_one_decision_per_approver');
            $table->index('value_version_id');
        });

        $decision = implode(',', array_map(fn (string $v): string => "'{$v}'", self::DECISION_VALUES));

        DB::statement(
            "ALTER TABLE configuration.approvals ADD CONSTRAINT approvals_decision_check CHECK (decision IN ({$decision}))"
        );

        DB::statement(
            'ALTER TABLE configuration.approvals ADD CONSTRAINT approvals_rejected_requires_motif_check '
            ."CHECK (decision <> 'rejected' OR (motif IS NOT NULL AND motif <> ''))"
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_approvals_self_approval()
            RETURNS trigger AS $$
            DECLARE
                author_id uuid;
            BEGIN
                SELECT author_person_account_link_id INTO author_id
                FROM configuration.value_versions
                WHERE id = NEW.value_version_id;

                IF author_id = NEW.approver_person_account_link_id THEN
                    RAISE EXCEPTION 'configuration: l''auteur d''une ValueVersion ne peut être son propre approbateur (ADR-0002 §3.2, §3.3)';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER approvals_prevent_self_approval BEFORE INSERT ON configuration.approvals '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_approvals_self_approval()'
        );

        // Une décision d'approbation est un fait historique : jamais modifiée
        // ni supprimée physiquement, sur le modèle exact de
        // `prevent_grants_deletion`.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION configuration.prevent_approvals_mutation()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'configuration: une décision d''approbation ne peut jamais être modifiée ni supprimée (ADR-0002 §8)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER approvals_prevent_update BEFORE UPDATE ON configuration.approvals '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_approvals_mutation()'
        );

        DB::statement(
            'CREATE TRIGGER approvals_prevent_deletion BEFORE DELETE ON configuration.approvals '
            .'FOR EACH ROW EXECUTE FUNCTION configuration.prevent_approvals_mutation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS approvals_prevent_deletion ON configuration.approvals');
        DB::statement('DROP TRIGGER IF EXISTS approvals_prevent_update ON configuration.approvals');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_approvals_mutation()');
        DB::statement('DROP TRIGGER IF EXISTS approvals_prevent_self_approval ON configuration.approvals');
        DB::statement('DROP FUNCTION IF EXISTS configuration.prevent_approvals_self_approval()');
        Schema::dropIfExists('configuration.approvals');
    }
};
