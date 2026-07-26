<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favori sur une publicité (Lot 3 Phase A, décision de Koné 2026-07-26).
 * Même raisonnement que `campaign_version_likes` : cible `CampaignVersion`,
 * un favori par personne par cible, re-cliquer retire. Signal social pur,
 * aucun effet financier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising.campaign_version_favorites', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_version_id')
                ->constrained('advertising.campaign_versions')
                ->cascadeOnDelete();
            $table->foreignUuid('person_account_link_id')
                ->constrained('identity.person_account_links')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['campaign_version_id', 'person_account_link_id']);
            $table->index('campaign_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.campaign_version_favorites');
    }
};
