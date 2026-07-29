<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerts.institution_dispatches` (P008-A, ecosystem/institutions/01 §6) :
 * transmission d'un dossier à une organisation affiliée. La machine d'états
 * suit exactement le tableau « Preuves et statuts » de ecosystem/institutions
 * §6 : « la transmission n'est pas une réception ; la réception n'est pas
 * une acceptation ; l'acceptation n'est pas une intervention réussie » —
 * jamais transformé automatiquement (déclencheur dans la migration
 * `..._add_alerts_state_machine_triggers`).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = [
        'created', 'transmitted', 'received', 'accepted', 'processing', 'resolved',
        'unanswered', 'refused', 'transferred', 'cancelled', 'expired', 'impossible', 'closed_unresolved',
    ];

    public function up(): void
    {
        Schema::create('alerts.institution_dispatches', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('case_id')
                ->constrained('alerts.cases')
                ->restrictOnDelete();

            $table->foreignUuid('organization_id')
                ->constrained('identity.organizations')
                ->restrictOnDelete();

            $table->string('territory_code')->nullable();
            $table->string('category');
            $table->string('state')->default('created');
            $table->string('channel')->default('in_app_portal');
            $table->string('correlation_id');

            $table->foreignUuid('responsible_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('transmitted_at')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('processing_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->text('error_detail')->nullable();

            $table->timestamps();

            $table->index(['organization_id', 'state']);
        });

        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));
        DB::statement("ALTER TABLE alerts.institution_dispatches ADD CONSTRAINT institution_dispatches_state_check CHECK (state IN ({$state}))");

        // Un dossier n'est jamais routé deux fois activement vers la même
        // organisation (ecosystem/alertes/02 test attendu : « doublon de
        // dispatch refusé ou idempotent ») — un transfert ou une annulation
        // libère la place pour un futur re-routage légitime.
        DB::statement(
            'CREATE UNIQUE INDEX institution_dispatches_one_active_per_org ON alerts.institution_dispatches (case_id, organization_id) '
            ."WHERE state NOT IN ('cancelled', 'transferred', 'refused', 'expired', 'impossible')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts.institution_dispatches');
    }
};
