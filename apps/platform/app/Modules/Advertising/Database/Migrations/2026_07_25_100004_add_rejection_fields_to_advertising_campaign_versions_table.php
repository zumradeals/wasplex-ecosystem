<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CampaignVersionState (ADR-0010 §3) n'a pas d'état `rejected` — seulement
 * draft/in_review/approved/suspended/retired. P005-C interprète le refus
 * d'une version en revue comme un retour à `draft` pour révision par son
 * auteur (transition d'état existante, comme `submitForReview`/`suspend`/
 * `retire` — aucun nouvel état ajouté), avec le motif enregistré ici, sur la
 * ligne elle-même : ce sont déjà, avant ce lot, des champs de cycle de vie
 * explicitement exclus de la protection du déclencheur
 * `campaign_versions_prevent_semantic_mutation` (state,
 * approver_person_account_link_id, approved_at, retired_at) ; y ajouter le
 * motif et l'horodatage du refus suit exactement le même principe plutôt
 * que d'introduire une nouvelle table (hors périmètre : ce lot ne construit
 * ni ReportCase ni ModerationCase supplémentaire, ADR-0010 §8).
 *
 * Un refus ne s'applique qu'à une version encore `in_review` (jamais
 * `approved`) : la ligne n'a donc jamais atteint l'état protégé par le
 * déclencheur au moment où ces deux colonnes sont écrites.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertising.campaign_versions', function (Blueprint $table): void {
            $table->text('rejection_reason')->nullable()->after('retired_at');
            $table->timestampTz('rejected_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('advertising.campaign_versions', function (Blueprint $table): void {
            $table->dropColumn(['rejection_reason', 'rejected_at']);
        });
    }
};
