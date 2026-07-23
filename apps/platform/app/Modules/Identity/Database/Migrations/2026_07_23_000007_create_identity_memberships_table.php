<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Appartenance nominative reliant une organisation à une liaison
 * personne-compte existante (P003-A §6). N'accorde par elle-même aucune
 * capacité (ADR-0004 §5, §22).
 *
 * Référence `identity.person_account_links` plutôt que `person_id` et
 * `user_id` séparément : une appartenance ne peut donc jamais associer le
 * compte d'une personne à l'identité d'une autre (revue SIRR P003-A.2 §1).
 *
 * Historique figé localement : les valeurs de statut ci-dessous ne doivent
 * jamais être remplacées par une référence à l'enum applicatif
 * MembershipStatus, afin qu'une évolution future de cet enum ne modifie pas
 * le comportement de cette migration déjà exécutée (revue SIRR P003-A.2 §3).
 *
 * `organization_id` est restreinte (`restrictOnDelete`), pas en cascade :
 * une organisation possède déjà un cycle d'état explicite incluant `closed`,
 * et sa suppression physique ne doit jamais effacer silencieusement
 * l'historique de ses appartenances (revue SIRR P003-A.3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATUS_VALUES = ['invited', 'pending', 'active', 'suspended', 'expired', 'revoked'];

    public function up(): void
    {
        Schema::create('identity.memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();
            $table->foreignUuid('organization_id')->constrained('identity.organizations')->restrictOnDelete();
            $table->string('status');
            $table->string('title')->nullable();
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('person_account_link_id');
        });

        $status = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            self::STATUS_VALUES,
        ));

        DB::statement(
            "ALTER TABLE identity.memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ({$status}))"
        );

        DB::statement(
            'ALTER TABLE identity.memberships ADD CONSTRAINT memberships_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        // Empêche au minimum deux appartenances actives identiques pour la
        // même liaison personne-compte et la même organisation.
        DB::statement(
            "CREATE UNIQUE INDEX memberships_one_active_per_link_and_organization ON identity.memberships (person_account_link_id, organization_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.memberships');
    }
};
