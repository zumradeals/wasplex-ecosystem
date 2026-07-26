<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « J'aime » sur une publicité (Lot 3 Phase A, menu vertical du Feed,
 * décision de Koné 2026-07-26). Cible `CampaignVersion`, pas `Campaign` :
 * c'est la création exacte — titre, format — que le Feed affiche et que
 * l'immuabilité de version (ADR-0010 §3) garantit stable ; si une nouvelle
 * version est approuvée, elle porte sa propre création et donc ses propres
 * signaux, jamais hérités silencieusement de l'ancienne.
 *
 * Un like par personne par cible (`unique`) : re-cliquer retire (toggle),
 * jamais une accumulation. Signal social pur — aucune colonne ne référence
 * le Ledger, aucun effet financier (voir `SocialEngagementService`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising.campaign_version_likes', function (Blueprint $table): void {
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
        Schema::dropIfExists('advertising.campaign_version_likes');
    }
};
