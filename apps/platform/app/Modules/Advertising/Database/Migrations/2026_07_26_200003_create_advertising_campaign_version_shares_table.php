<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intention de partage d'une publicité (Lot 3 Phase A, décision de Koné
 * 2026-07-26) : le partage effectif se produit hors plateforme (partage
 * natif du système d'exploitation) — cette table ne compte que le geste
 * « j'ai déclenché un partage », jamais le destinataire ni le canal, ce qui
 * reste cohérent avec AMD-0001 (donnée personnelle jamais exposée comme
 * base de contacts) et AMD-0009 (aucun profilage de qui est contacté).
 *
 * Pas d'unicité (contrairement aux likes/favoris) : partager plusieurs
 * fois la même publicité est un geste légitime répété, pas un état à
 * bascule. Chaque ligne est un événement, jamais un solde muté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising.campaign_version_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_version_id')
                ->constrained('advertising.campaign_versions')
                ->cascadeOnDelete();
            $table->foreignUuid('person_account_link_id')
                ->constrained('identity.person_account_links')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index('campaign_version_id');
            $table->index(['campaign_version_id', 'person_account_link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.campaign_version_shares');
    }
};
