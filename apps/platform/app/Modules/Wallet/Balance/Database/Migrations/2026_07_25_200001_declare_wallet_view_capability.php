<?php

use App\Modules\Governance\Authorization\Support\ScopeMatcher;
use App\Modules\Wallet\Balance\Http\Controllers\WalletBalanceController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `wallet.view` (P006-A) — première capacité et première route
 * réelles du module Wallet, sur le modèle exact des déclarations Publicité
 * (P005-B et suivants). Couvre exclusivement la consultation, par une
 * personne, de son propre solde WP disponible
 * ({@see WalletBalanceController}) —
 * ferme la porte de reprise posée par TD-0003-A (aucune capacité
 * `wallet.*` ne gouvernait encore une route réelle) pour cette seule route.
 *
 * Portée des grants réels — exclusivement `self` (`ResourceContext.ownerPersonId`
 * doit être la personne authentifiée elle-même, {@see ScopeMatcher}) :
 * consulter le solde WP d'une autre personne n'est délibérément pas couvert
 * par cette capacité (cela resterait un accès sensible à finalité obligatoire,
 * ADR-0004 §8, hors périmètre de ce lot).
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = read` : première capacité de tout l'écosystème à ce jour
 *   à consulter plutôt qu'écrire — aucune transaction Ledger n'est produite
 *   par cette route.
 * - `risk_class = ordinary` : consulter son propre solde n'engage, ne
 *   suspend ni n'expose aucune valeur ou donnée appartenant à autrui — même
 *   niveau que `campaign.create` (auto-service sur sa propre ressource).
 * - `purpose_required = false` : ADR-0004 §8 réserve la finalité
 *   obligatoire à « toute consultation ou export sensible » — au sens
 *   d'ADR-0004 §8, la sensibilité visée est celle d'un accès à la donnée
 *   d'autrui (dossier, enquête, audit) ; une personne consultant sa propre
 *   position financière, déjà strictement scopée `self`, n'ajoute aucune
 *   garantie supplémentaire par une finalité distincte (même raisonnement
 *   que `campaign.create`).
 * - `approval_required = false` : aucune décision métier, seulement une
 *   lecture de projection déjà publique pour son propriétaire.
 * - `minimum_session_assurance = weak` : même plancher que toutes les
 *   capacités déclarées jusqu'ici (TD-0002-A, `standard` structurellement
 *   inatteignable) — une consultation sans effet ne justifierait de toute
 *   façon pas un palier plus élevé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'wallet.view',
            'version' => 1,
            'domain' => 'wallet',
            'action' => 'view',
            'description' => 'Consulter son propre solde WP disponible (ecosystem/wallet/01 §3, §7).',
            'operation' => 'read',
            'risk_class' => 'ordinary',
            'purpose_required' => false,
            'approval_required' => false,
            'minimum_session_assurance' => 'weak',
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('governance.capability_definitions')->where('stable_key', 'wallet.view')->delete();
    }
};
