<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `alerts.restitutions` (P008-A, ecosystem/institutions/01 §8) : remise
 * sécurisée d'un bien retrouvé. Le code est stocké sous forme de condensat,
 * jamais en clair ; remise et réception sont deux événements distincts,
 * confirmés séparément. Le témoin facultatif ne reçoit aucune capacité
 * générale (ne devient pas un acteur Wasplex).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATE_VALUES = [
        'pending', 'code_issued', 'delivered', 'received', 'completed', 'disputed', 'expired', 'cancelled',
    ];

    public function up(): void
    {
        Schema::create('alerts.restitutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('case_id')
                ->constrained('alerts.cases')
                ->restrictOnDelete();

            $table->foreignUuid('correspondence_report_id')
                ->nullable()
                ->constrained('alerts.correspondence_reports')
                ->restrictOnDelete();

            $table->foreignUuid('organization_id')
                ->nullable()
                ->constrained('identity.organizations')
                ->restrictOnDelete();

            $table->string('state')->default('pending');

            $table->string('code_hash')->nullable();
            $table->timestampTz('code_expires_at')->nullable();

            $table->timestampTz('delivered_at')->nullable();
            $table->foreignUuid('delivered_confirmed_by_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->timestampTz('received_at')->nullable();
            $table->foreignUuid('received_confirmed_by_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->foreignUuid('witness_person_account_link_id')
                ->nullable()
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->text('dispute_reason')->nullable();

            $table->timestamps();

            $table->index(['case_id', 'state']);
        });

        $state = implode(',', array_map(fn (string $v): string => "'{$v}'", self::STATE_VALUES));
        DB::statement("ALTER TABLE alerts.restitutions ADD CONSTRAINT restitutions_state_check CHECK (state IN ({$state}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts.restitutions');
    }
};
