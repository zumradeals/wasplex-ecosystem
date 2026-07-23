<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grant nominatif : capacité, portée, finalité, conditions, validité et
 * source (ADR-0004 §5, §22). Sujet exactement de un type.
 *
 * Historique figé localement (revue SIRR P003-A.2 §3).
 *
 * Aucune suppression physique d'un grant ayant existé ; un grant révoqué ou
 * expiré ne redevient jamais actif par simple UPDATE (P003-B1 §9) : ces deux
 * garanties sont appliquées ici par déclencheurs PL/pgSQL, en défense en
 * profondeur des règles déjà imposées par le service GrantManager
 * (ADR-0004 §11).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const EFFECT_VALUES = ['allow', 'read_only', 'masked'];

    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['proposed', 'active', 'suspended', 'expired', 'revoked'];

    /**
     * @var list<string>
     */
    private const SOURCE_VALUES = ['direct', 'role_template', 'contract', 'decision', 'delegation', 'emergency'];

    public function up(): void
    {
        Schema::create('governance.grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();
            $table->foreignUuid('membership_id')
                ->nullable()
                ->constrained('identity.memberships')
                ->restrictOnDelete();

            $table->foreignUuid('capability_definition_id')
                ->constrained('governance.capability_definitions')
                ->restrictOnDelete();
            $table->foreignUuid('purpose_definition_id')
                ->nullable()
                ->constrained('governance.purpose_definitions')
                ->restrictOnDelete();
            $table->foreignUuid('policy_version_id')
                ->constrained('governance.policy_versions')
                ->restrictOnDelete();
            $table->foreignUuid('role_template_id')
                ->nullable()
                ->constrained('governance.role_templates')
                ->restrictOnDelete();

            $table->unsignedInteger('scope_schema_version');
            $table->jsonb('scope_payload');
            $table->unsignedInteger('conditions_schema_version');
            $table->jsonb('conditions_payload');

            $table->string('effect');
            $table->string('state');
            $table->string('source');
            $table->string('source_reference', 255)->nullable();

            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();

            $table->foreignUuid('author_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();
            $table->foreignUuid('approver_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revocation_reason', 500)->nullable();

            $table->timestamps();

            $table->index('capability_definition_id');
            $table->index('person_account_link_id');
            $table->index('membership_id');
            $table->index('state');
        });

        $effect = implode(',', array_map(fn (string $value): string => "'{$value}'", self::EFFECT_VALUES));
        $state = implode(',', array_map(fn (string $value): string => "'{$value}'", self::STATE_VALUES));
        $source = implode(',', array_map(fn (string $value): string => "'{$value}'", self::SOURCE_VALUES));

        DB::statement(
            "ALTER TABLE governance.grants ADD CONSTRAINT grants_effect_check CHECK (effect IN ({$effect}))"
        );

        DB::statement(
            "ALTER TABLE governance.grants ADD CONSTRAINT grants_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            "ALTER TABLE governance.grants ADD CONSTRAINT grants_source_check CHECK (source IN ({$source}))"
        );

        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_period_check CHECK (valid_until IS NULL OR valid_until > valid_from)'
        );

        // Exactement un sujet humain : liaison directe ou appartenance.
        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_exactly_one_subject_check '
            .'CHECK ((person_account_link_id IS NOT NULL)::int + (membership_id IS NOT NULL)::int = 1)'
        );

        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_author_not_approver_check '
            .'CHECK (approver_person_account_link_id IS NULL OR approver_person_account_link_id <> author_person_account_link_id)'
        );

        // Aucune activation sans que activated_at ne soit posé.
        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_active_requires_activated_at_check '
            ."CHECK (state <> 'active' OR activated_at IS NOT NULL)"
        );

        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_revoked_requires_revoked_at_check '
            ."CHECK ((state = 'revoked') = (revoked_at IS NOT NULL))"
        );

        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_scope_payload_is_object_check '
            ."CHECK (jsonb_typeof(scope_payload) = 'object')"
        );

        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_conditions_payload_is_object_check '
            ."CHECK (jsonb_typeof(conditions_payload) = 'object')"
        );

        DB::statement(
            'ALTER TABLE governance.grants ADD CONSTRAINT grants_scope_payload_size_check '
            .'CHECK (octet_length(scope_payload::text) <= 8192)'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_grants_deletion()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'governance: un grant ne peut jamais être supprimé physiquement (P003-B1 §9)';
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER grants_prevent_deletion BEFORE DELETE ON governance.grants '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_grants_deletion()'
        );

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION governance.prevent_grant_reactivation()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.state IN ('revoked', 'expired') AND NEW.state = 'active' THEN
                    RAISE EXCEPTION 'governance: un grant révoqué ou expiré ne peut jamais redevenir actif par UPDATE (P003-B1 §9)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(
            'CREATE TRIGGER grants_prevent_reactivation BEFORE UPDATE ON governance.grants '
            .'FOR EACH ROW EXECUTE FUNCTION governance.prevent_grant_reactivation()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS grants_prevent_reactivation ON governance.grants');
        DB::statement('DROP TRIGGER IF EXISTS grants_prevent_deletion ON governance.grants');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_grant_reactivation()');
        DB::statement('DROP FUNCTION IF EXISTS governance.prevent_grants_deletion()');
        Schema::dropIfExists('governance.grants');
    }
};
