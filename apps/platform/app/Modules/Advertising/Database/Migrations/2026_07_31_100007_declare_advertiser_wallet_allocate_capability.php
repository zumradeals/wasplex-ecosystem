<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertiser_wallet.allocate` (instruction explicite du fondateur,
 * 2026-07-31) : déplacement d'un montant du solde Wallet mutualisé de
 * l'annonceur vers le budget disponible d'une campagne précise — un simple
 * transfert interne (aucune couverture supplémentaire, aucun appel externe),
 * mais toujours une écriture Ledger réelle, donc une capacité `write` à part
 * entière, jamais un sous-effet silencieux d'une autre capacité.
 *
 * Mêmes dimensions que `advertiser_wallet.deposit` (migration `2026_07_31_100005`) :
 *
 * - `operation = write` : produit une transaction Ledger équilibrée
 *   (`AdvertiserWalletService::allocateToCampaign()`).
 * - Portée `self` : un annonceur ne peut allouer que le solde de son propre
 *   dossier vers ses propres campagnes — vérifié via `ownerPersonId`, même
 *   forme que `campaign.fund_self`.
 * - `risk_class = ordinary` : un transfert interne entre deux comptes déjà
 *   couverts par un dépôt confirmé n'introduit aucune valeur nouvelle ; le
 *   risque réel (crédit indu) est déjà couvert à l'étape du dépôt, pas ici.
 * - `purpose_required = false` / `approval_required = false` /
 *   `minimum_session_assurance = weak` : mêmes raisons que
 *   `advertiser_wallet.deposit`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertiser_wallet.allocate',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'allocate',
            'description' => "Allouer un montant du solde Wallet de son propre dossier annonceur vers le budget disponible d'une de ses campagnes.",
            'operation' => 'write',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertiser_wallet.allocate')->delete();
    }
};
