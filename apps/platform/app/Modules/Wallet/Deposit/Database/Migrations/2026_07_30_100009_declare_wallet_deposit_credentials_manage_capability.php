<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `wallet_deposit.manage_credentials` (véto du dirigeant, 2026-07-30) :
 * écran admin de configuration des clés GeniusPay
 * (`AdminWalletDepositCredentialsController`), qui referme l'écart documenté
 * par TD-0008-A (« aucune configuration de production réelle » — la porte de
 * reprise décrite y était jusqu'ici l'environnement seul ; ce lot ajoute une
 * seconde porte, la configuration admin chiffrée en base, sans supprimer la
 * première : `GeniusPayCredentialsResolver` retombe sur `config('services.
 * geniuspay')` si aucune ligne n'existe encore).
 *
 * Distincte de `wallet_deposit.review` (lecture seule des dépôts en litige,
 * migration `2026_07_30_100006`) : ici l'opération modifie un secret vivant
 * qui conditionne tous les appels GeniusPay à venir — jamais la même
 * capacité qu'une simple consultation.
 *
 * Raisonnement des dimensions retenues :
 *
 * - `operation = write` : remplace un secret persisté, jamais une lecture.
 * - `risk_class = critical` : même classe que `campaign.fund`/`event.accept`
 *   — une clé API erronée ou compromise affecte tout dépôt Wallet suivant,
 *   pas une ressource isolée (même raisonnement que ces deux capacités,
 *   migrations `2026_07_25_100008`/`2026_07_25_100010`).
 * - `purpose_required = false` : même écart que `wallet_deposit.review`
 *   (TD-0008-D) — aucune finalité de configuration technique n'est encore
 *   catalguée ; la rendre obligatoire sans définition rendrait l'écran
 *   inutilisable pour une seule capacité de bootstrap.
 * - `approval_required = false` : même choix que `campaign.fund`/
 *   `event.accept` — aucun mécanisme d'approbation dédié n'existe pour une
 *   capacité personnel hors libre-service ; l'octroi initial passe déjà par
 *   `GrantManager::activate()` (auteur ≠ approbateur ≠ sujet, sauf
 *   auto-amorçage Administrateur Système, addendum ADR-0004).
 * - `minimum_session_assurance = strong` : même palier que `campaign.fund`/
 *   `event.accept` — une reconfirmation de mot de passe (ou passkey)
 *   récente est exigée avant toute écriture de secret prestataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'wallet_deposit.manage_credentials',
            'version' => 1,
            'domain' => 'wallet',
            'action' => 'manage_credentials',
            'description' => 'Configurer les clés API GeniusPay (base_url, api_key, api_secret, webhook_secret) utilisées par le prestataire de dépôt Wallet.',
            'operation' => 'write',
            'risk_class' => 'critical',
            'purpose_required' => false,
            'approval_required' => false,
            'minimum_session_assurance' => 'strong',
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('governance.capability_definitions')->where('stable_key', 'wallet_deposit.manage_credentials')->delete();
    }
};
