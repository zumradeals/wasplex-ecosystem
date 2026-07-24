<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CampaignVersion : liaison indivisible créations/audience/prix/événement
 * attendu/rémunération/destination/durée (ADR-0010 §3,
 * `02-preuves-moderation-et-destinations.md` §2). Immuable une fois
 * approuvée — voir la migration `add_advertising_campaign_version_immutability_triggers`.
 *
 * Aucune formule de prix ou de récompense n'est codée en dur (ADR-0010 §4) :
 * `pricing_configuration_key`/`version` et `reward_configuration_key`/`version`
 * ne référencent qu'une configuration future, sur le modèle exact de
 * `ledger.ledger_transactions.configuration_key`.
 *
 * Historique figé localement, sur le modèle de `governance.grants`
 * (P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'in_review', 'approved', 'suspended', 'retired'];

    public function up(): void
    {
        Schema::create('advertising.campaign_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_id')
                ->constrained('advertising.campaigns')
                ->restrictOnDelete();
            $table->integer('version');
            $table->string('state');

            $table->foreignUuid('sector_classification_id')
                ->constrained('advertising.sector_classifications')
                ->restrictOnDelete();

            $table->jsonb('creations');
            $table->jsonb('expected_event');
            $table->jsonb('destination');
            $table->jsonb('territory');

            $table->string('pricing_configuration_key')->nullable();
            $table->unsignedInteger('pricing_configuration_version')->nullable();
            $table->string('reward_configuration_key')->nullable();
            $table->unsignedInteger('reward_configuration_version')->nullable();

            $table->timestampTz('valid_from');
            $table->timestampTz('valid_until')->nullable();

            $table->foreignUuid('author_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();
            $table->foreignUuid('approver_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('retired_at')->nullable();

            $table->timestamps();

            $table->unique(['campaign_id', 'version']);
            $table->index('sector_classification_id');
            $table->index('state');
        });

        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement(
            "ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_positive_version_check CHECK (version > 0)'
        );

        DB::statement(
            'ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_period_check CHECK (valid_until IS NULL OR valid_until > valid_from)'
        );

        // Défense en profondeur inconditionnelle, sur le modèle de
        // grants_author_not_approver_check : l'exigence d'un approbateur
        // distinct pour les secteurs à revue renforcée (ADR-0010 §5) est
        // appliquée par CampaignVersionService, qui connaît le niveau de
        // revue résolu ; cette contrainte garantit dans tous les cas qu'un
        // approbateur, s'il existe, n'est jamais l'auteur.
        DB::statement(
            'ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_author_not_approver_check '
            .'CHECK (approver_person_account_link_id IS NULL OR approver_person_account_link_id <> author_person_account_link_id)'
        );

        // Sens unique, pas une équivalence : une fois posé, approved_at
        // reste une trace historique même après une transition ultérieure
        // vers suspended/retired (qui ne l'efface jamais).
        DB::statement(
            'ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_approved_requires_approved_at_check '
            ."CHECK (state <> 'approved' OR approved_at IS NOT NULL)"
        );

        foreach (['creations', 'expected_event', 'destination'] as $column) {
            DB::statement(
                "ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_{$column}_is_object_check "
                ."CHECK (jsonb_typeof({$column}) = 'object')"
            );
        }

        DB::statement(
            'ALTER TABLE advertising.campaign_versions ADD CONSTRAINT campaign_versions_territory_is_array_check '
            ."CHECK (jsonb_typeof(territory) = 'array')"
        );

        // Au plus une version couramment approuvée par campagne : une
        // nouvelle approbation retire d'abord l'ancienne (transition
        // d'état), jamais deux versions "vivantes" simultanées, sur le
        // modèle de capability_definitions_one_active_per_key.
        DB::statement(
            'CREATE UNIQUE INDEX campaign_versions_one_approved_per_campaign ON advertising.campaign_versions (campaign_id) '
            ."WHERE state = 'approved'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.campaign_versions');
    }
};
