<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertiser_wallet.deposit` (instruction explicite du fondateur,
 * 2026-07-31) : initiation d'un dépôt en libre-service dans le solde
 * annonceur mutualisé via GeniusPay. Mêmes dimensions et même raisonnement
 * que `campaign.fund_self` (migration `2026_07_30_300004`), reprises terme
 * à terme :
 *
 * - `operation = write` : crée une ligne `advertising.advertiser_wallet_deposits`
 *   et appelle un prestataire externe réel, même si aucune valeur Ledger ne
 *   bouge encore à cet instant.
 * - Portée `self` : un annonceur ne recharge jamais le Wallet d'un autre
 *   dossier annonceur — vérifié via `ownerPersonId` (représentant du
 *   dossier annonceur), même forme que `campaign.fund_self`.
 * - `risk_class = ordinary` : comme `campaign.fund_self`/`wallet.deposit`,
 *   une capacité `self` destinée à l'octroi automatique via `user.base` ne
 *   peut de toute façon jamais être `sensitive`/`critical`. La protection
 *   réelle contre un crédit indu reste la vérification de signature HMAC du
 *   webhook et le rapprochement de montant, jamais le niveau de risque de
 *   cette seule capacité d'initiation.
 * - `purpose_required = false` / `approval_required = false` /
 *   `minimum_session_assurance = weak` : mêmes raisons que
 *   `campaign.fund_self`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertiser_wallet.deposit',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'deposit',
            'description' => 'Initier un dépôt dans le solde Wallet mutualisé de son propre dossier annonceur via GeniusPay, en libre-service.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertiser_wallet.deposit')->delete();
    }
};
