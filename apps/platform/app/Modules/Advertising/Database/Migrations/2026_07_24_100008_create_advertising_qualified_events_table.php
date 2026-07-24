<?php

use App\Modules\Advertising\Services\CampaignBudgetService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QualifiedEvent (ADR-0010 §3, `01-cycle-creation-valeur.md` §3-4) :
 * identifiant, campagne/version, format, preuve, horodatage, décision
 * anti-fraude graduée, règle de prix appliquée, statut anti-double-
 * facturation, corrélation, clé d'idempotence obligatoire.
 *
 * Les colonnes `*_transaction_id` référencent les transactions Ledger
 * exactes de chaque étape (§4 : réservation, consommation+répartition,
 * libération), jamais une écriture directe — voir
 * {@see CampaignBudgetService}.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const FRAUD_DECISION_VALUES = ['none', 'anomaly', 'weak_suspicion', 'serious_suspicion', 'confirmed_fraud'];

    /**
     * @var list<string>
     */
    private const BILLING_STATUS_VALUES = ['pending', 'accepted', 'rejected'];

    public function up(): void
    {
        Schema::create('advertising.qualified_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_id')
                ->constrained('advertising.campaigns')
                ->restrictOnDelete();
            $table->foreignUuid('campaign_version_id')
                ->constrained('advertising.campaign_versions')
                ->restrictOnDelete();

            $table->string('format');
            $table->jsonb('evidence');
            $table->timestampTz('occurred_at');

            $table->string('fraud_decision');
            $table->bigInteger('applied_price_amount');
            $table->char('applied_price_currency', 3);
            $table->string('pricing_configuration_key')->nullable();
            $table->unsignedInteger('pricing_configuration_version')->nullable();

            $table->string('billing_status');

            $table->foreignUuid('reservation_transaction_id')
                ->constrained('ledger.ledger_transactions')
                ->restrictOnDelete();
            $table->foreignUuid('consumption_transaction_id')
                ->nullable()
                ->constrained('ledger.ledger_transactions')
                ->restrictOnDelete();
            $table->foreignUuid('distribution_transaction_id')
                ->nullable()
                ->constrained('ledger.ledger_transactions')
                ->restrictOnDelete();
            $table->foreignUuid('release_transaction_id')
                ->nullable()
                ->constrained('ledger.ledger_transactions')
                ->restrictOnDelete();

            $table->uuid('correlation_id');
            $table->string('idempotency_key')->unique();

            $table->timestamps();

            $table->index('campaign_id');
            $table->index('campaign_version_id');
            $table->index('billing_status');
        });

        $fraudDecision = implode(',', array_map(fn (string $v): string => "'{$v}'", self::FRAUD_DECISION_VALUES));
        $billingStatus = implode(',', array_map(fn (string $v): string => "'{$v}'", self::BILLING_STATUS_VALUES));

        DB::statement(
            "ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_fraud_decision_check CHECK (fraud_decision IN ({$fraudDecision}))"
        );

        DB::statement(
            "ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_billing_status_check CHECK (billing_status IN ({$billingStatus}))"
        );

        DB::statement(
            'ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_applied_price_positive_check CHECK (applied_price_amount > 0)'
        );

        DB::statement(
            "ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_applied_price_currency_format_check CHECK (applied_price_currency ~ '^[A-Z]{3}$')"
        );

        DB::statement(
            'ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_evidence_is_object_check '
            ."CHECK (jsonb_typeof(evidence) = 'object')"
        );

        // Cohérence entre statut et transactions Ledger posées : un
        // événement accepté a nécessairement consommé et réparti ; un
        // événement rejeté a nécessairement libéré ; un événement encore
        // pending n'a ni l'un ni l'autre.
        DB::statement(
            'ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_accepted_requires_consumption_and_distribution_check '
            ."CHECK ((billing_status = 'accepted') = (consumption_transaction_id IS NOT NULL AND distribution_transaction_id IS NOT NULL))"
        );

        DB::statement(
            'ALTER TABLE advertising.qualified_events ADD CONSTRAINT qualified_events_rejected_requires_release_check '
            ."CHECK ((billing_status = 'rejected') = (release_transaction_id IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.qualified_events');
    }
};
