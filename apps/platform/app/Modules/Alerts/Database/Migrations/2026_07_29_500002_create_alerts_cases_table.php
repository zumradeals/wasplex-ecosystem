<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerts.cases` (P008-A, ecosystem/alertes/02 §1, §3) : dossier source,
 * confidentiel, distinct de sa projection publique (`alerts.publications`).
 * Deux natures partagent ce registre — `community` (ecosystem/alertes/02 §7,
 * UX-0001 §20) et `sos` (ecosystem/alertes/02 §3, machine d'états déjà
 * adoptée) — avec des machines d'états disjointes appliquées ici par
 * déclencheur selon `nature`, jamais mélangées.
 *
 * `author_person_account_link_id` est nullable : un SOS peut être créé sans
 * authentification (AMD-0007 §2, Constitution art. 14.2).
 * `exact_location` n'est jamais recopié dans une projection publique
 * (ecosystem/alertes/02 §5) — colonne distincte, jamais lue par le Feed.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const NATURE_VALUES = ['community', 'sos'];

    /**
     * @var list<string>
     */
    private const CATEGORY_VALUES = [
        'lost_item', 'found_item', 'lost_document', 'found_document',
        'stolen_vehicle', 'found_vehicle', 'missing_person', 'found_person',
        'fire', 'accident', 'medical_emergency', 'robbery_in_progress',
    ];

    /**
     * @var list<string>
     */
    private const VERIFICATION_LEVEL_VALUES = ['unverified', 'reviewed', 'verified'];

    /**
     * Union des deux machines d'états (ecosystem/alertes/02 §3, §7 ; UX-0001
     * §20) — jamais mélangées entre elles pour un même dossier, voir le
     * déclencheur `cases_enforce_state_machine` (migration
     * `..._add_alerts_state_machine_triggers`).
     *
     * @var list<string>
     */
    private const STATE_VALUES = [
        // community
        'draft', 'submitted', 'under_review', 'published', 'restricted', 'rejected',
        'matched', 'restitution_scheduled', 'resolved', 'disputed', 'expired', 'withdrawn',
        // sos (partage `disputed`/`expired` avec community)
        'created', 'transmitted', 'received', 'accepted', 'processing',
        'unanswered', 'refused', 'transferred', 'cancelled', 'impossible', 'closed_unresolved',
    ];

    public function up(): void
    {
        Schema::create('alerts.cases', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('author_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->string('nature');
            $table->string('category');
            $table->string('verification_level')->default('unverified');
            $table->string('state');

            $table->string('country_code', 2);
            $table->string('territory_code')->nullable();

            // Jamais public, jamais recopié dans `alerts.publications`.
            $table->jsonb('exact_location')->nullable();

            $table->text('source_description');
            $table->string('recall_phone')->nullable();
            $table->string('locale', 5)->default('fr');

            $table->string('policy_reference')->nullable();
            $table->string('idempotency_key')->nullable();

            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->string('closure_reason', 500)->nullable();

            $table->timestamps();

            $table->unique('idempotency_key');
            $table->index(['nature', 'state']);
            $table->index(['country_code', 'territory_code', 'category']);
        });

        $nature = implode(',', array_map(fn (string $v): string => "'{$v}'", self::NATURE_VALUES));
        $category = implode(',', array_map(fn (string $v): string => "'{$v}'", self::CATEGORY_VALUES));
        $verification = implode(',', array_map(fn (string $v): string => "'{$v}'", self::VERIFICATION_LEVEL_VALUES));
        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));

        DB::statement("ALTER TABLE alerts.cases ADD CONSTRAINT cases_nature_check CHECK (nature IN ({$nature}))");
        DB::statement("ALTER TABLE alerts.cases ADD CONSTRAINT cases_category_check CHECK (category IN ({$category}))");
        DB::statement("ALTER TABLE alerts.cases ADD CONSTRAINT cases_verification_level_check CHECK (verification_level IN ({$verification}))");
        DB::statement("ALTER TABLE alerts.cases ADD CONSTRAINT cases_state_check CHECK (state IN ({$state}))");

        // Une `Definition` communautaire (`lost_item`...`found_person`) ne
        // peut jamais porter un état SOS et réciproquement — défense en
        // profondeur du déclencheur de machine d'états.
        DB::statement(<<<'SQL'
            ALTER TABLE alerts.cases ADD CONSTRAINT cases_nature_category_check CHECK (
                (nature = 'community' AND category IN (
                    'lost_item', 'found_item', 'lost_document', 'found_document',
                    'stolen_vehicle', 'found_vehicle', 'missing_person', 'found_person'
                )) OR
                (nature = 'sos' AND category IN (
                    'fire', 'accident', 'medical_emergency', 'robbery_in_progress'
                ))
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts.cases');
    }
};
