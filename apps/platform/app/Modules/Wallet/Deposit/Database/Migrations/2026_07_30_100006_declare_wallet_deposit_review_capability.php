<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `wallet_deposit.review` (TD-0008-D : « aucun écran admin n'expose
 * les dépôts `unknown_reconciliation` ou les webhooks à signature invalide/
 * répétée pour une revue humaine »). Couvre exclusivement la consultation,
 * par du personnel Wasplex habilité, des dépôts en litige et des entrées
 * d'inbox webhook GeniusPay à signature invalide — jamais leur résolution
 * (aucune action de contre-écriture, de relance provider ni de
 * réconciliation automatique n'est construite par ce lot ; voir
 * `AdminWalletDepositController`).
 *
 * Distincte de `wallet.deposit` (initiation par la personne elle-même,
 * portée `self`, migration `2026_07_30_100004`) : ici la personne consultée
 * n'est jamais l'appelant, d'où une capacité et une portée séparées — même
 * séparation que `campaign.fund`/`campaign.create` ou `alert_case.review`/
 * `alert_case.submit`.
 *
 * Portée des grants réels — `resource_type = wallet.deposit` sans
 * `resource_ids` (personnel de supervision, jamais limité à un dépôt
 * particulier) — même forme que `alerts.case_category` pour
 * `alert_case.review`.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = read` : aucune écriture, cet écran ne fait qu'exposer un
 *   état déjà persisté par le webhook (ecosystem/wallet/05 §4).
 * - `risk_class = sensitive` : contrairement à `access.view`/
 *   `configuration.view` (données internes de gouvernance), la donnée
 *   exposée ici est la position financière personnelle d'une personne
 *   déterminée (montant, référence prestataire) consultée par un tiers
 *   membre du personnel — ADR-0004 §8 qualifie explicitement ce type
 *   d'accès à la donnée d'autrui de sensible (même raisonnement que
 *   `alert_case.review`, qui expose des descriptions de dossiers
 *   personnels).
 * - `purpose_required = false` : le moteur d'autorisation complet est
 *   désormais appliqué par `AdminWalletDepositController` (portée, période,
 *   politique, assurance et audit). Le catalogue de finalités ne déclare
 *   toutefois pas encore une finalité versionnée de rapprochement financier ;
 *   la rendre obligatoire sans définition ni liaison rendrait l'écran
 *   inutilisable. Cet écart reste explicitement suivi dans TD-0008-D et
 *   devra être fermé avant l'ouverture à une équipe finance élargie.
 * - `approval_required = false` : simple lecture, aucune décision.
 * - `minimum_session_assurance = weak` : même plancher que toutes les
 *   capacités déjà déclarées pour ce portail (TD-0002-A, `standard`
 *   structurellement inatteignable depuis une session HTTP réelle à ce
 *   jour).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'wallet_deposit.review',
            'version' => 1,
            'domain' => 'wallet',
            'action' => 'review',
            'description' => 'Consulter les dépôts Wallet en litige (unknown_reconciliation) et les webhooks GeniusPay à signature invalide, pour revue humaine (TD-0008-D).',
            'operation' => 'read',
            'risk_class' => 'sensitive',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'wallet_deposit.review')->delete();
    }
};
