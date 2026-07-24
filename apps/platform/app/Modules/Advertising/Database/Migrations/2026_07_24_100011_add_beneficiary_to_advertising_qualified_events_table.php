<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correction d'anomalie bloquante identifiée en revue (post P005-A) :
 * `01-cycle-creation-valeur.md` §4 (« aucune rémunération utilisateur sans
 * preuve acceptée ») et §10 (« ce que l'utilisateur a volontairement
 * accompli ») décrivent toujours un événement qualifié comme rémunérant
 * une personne précise, jamais un agrégat anonyme — sous-spécification
 * d'ADR-0010 §3, qui n'énumérait pas ce champ explicitement.
 *
 * `beneficiary_person_account_link_id` référence le même sujet que
 * Governance/Authorization (P003-B2, `AuthenticatedSubject::$personAccountLink`) :
 * une liaison personne-compte réelle, jamais un identifiant applicatif
 * libre. NOT NULL dès la création : un QualifiedEvent sans bénéficiaire
 * est refusé, pas seulement déconseillé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising.qualified_events', function (Blueprint $table): void {
            $table->foreignUuid('beneficiary_person_account_link_id')
                ->after('campaign_version_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->index('beneficiary_person_account_link_id');
        });
    }

    public function down(): void
    {
        Schema::table('advertising.qualified_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('beneficiary_person_account_link_id');
        });
    }
};
