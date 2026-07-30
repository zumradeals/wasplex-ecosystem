<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.fund_self` (véto du dirigeant, 2026-07-30) : financement
 * de campagne en libre-service par l'annonceur via GeniusPay. Couvre
 * exclusivement l'initiation (création d'une intention de paiement) —
 * jamais le crédit lui-même, qui n'a lieu qu'après confirmation du webhook
 * signé (`CampaignFundingWebhookService`, hors autorisation par capacité :
 * authentifié par signature HMAC, pas par session, même principe que
 * `wallet.deposit`/`DepositWebhookController`).
 *
 * Distincte de `campaign.fund` (staff Wasplex, `critical`, jamais `self` —
 * migration `2026_07_25_100008`) : les deux coexistent pour deux moyens de
 * paiement différents (GeniusPay confirmé automatiquement vs paiement hors
 * ligne confirmé manuellement par le personnel finance).
 *
 * Raisonnement des dimensions retenues — repris terme à terme de
 * `wallet.deposit` (migration `2026_07_30_100004`), même situation exacte :
 *
 * - `operation = write` : crée une ligne `advertising.campaign_fundings` et
 *   appelle un prestataire externe réel, même si aucune valeur Ledger ne
 *   bouge encore à cet instant.
 * - Portée `self` : un annonceur ne finance jamais la campagne d'un autre
 *   annonceur — vérifié via `ownerPersonId` (représentant du dossier
 *   annonceur propriétaire de la campagne), même forme que `campaign.create`.
 * - `risk_class = ordinary` : comme `wallet.deposit`, une capacité `self`
 *   destinée à l'octroi automatique via `user.base` ne peut de toute façon
 *   jamais être `sensitive`/`critical` (`GrantAutoIssuer` appelle toujours
 *   `activate()` avec un approbateur nul, refusé par `GrantManager` au-delà
 *   d'`ordinary`). La protection réelle contre un crédit indu reste la
 *   vérification de signature HMAC du webhook et le rapprochement de
 *   montant, jamais le niveau de risque de cette seule capacité d'initiation.
 * - `purpose_required = false` / `approval_required = false` /
 *   `minimum_session_assurance = weak` : mêmes raisons que `wallet.deposit`
 *   — aucune revue humaine ni confirmation de session mid-parcours ne
 *   remplacerait la preuve externe déjà exigée par le webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.fund_self',
            'version' => 1,
            'domain' => 'campaign',
            'action' => 'fund_self',
            'description' => "Initier un financement de campagne (budget propre) via GeniusPay, en libre-service par l'annonceur.",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.fund_self')->delete();
    }
};
