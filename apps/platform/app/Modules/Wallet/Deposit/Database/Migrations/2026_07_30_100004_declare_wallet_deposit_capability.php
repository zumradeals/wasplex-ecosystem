<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `wallet.deposit` (AMD-0017 ; ecosystem/wallet/05). Couvre
 * exclusivement l'initiation d'un dépôt (création d'une intention de
 * paiement GeniusPay) — jamais le crédit lui-même, qui n'a lieu qu'après
 * confirmation du webhook signé (`DepositWebhookController`, hors
 * autorisation par capacité : authentifié par signature HMAC, pas par
 * session, ADR-0007 §11).
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : crée une ligne `ledger.wallet_deposits` et
 *   appelle un prestataire externe réel, même si aucune valeur Ledger ne
 *   bouge encore à cet instant.
 * - Portée `self` (comme `wallet.view`/`campaign.create`) : une personne
 *   ne recharge jamais le wallet d'autrui — aucune notion d'annonceur ou
 *   d'institution distincte ici, `PersonLedgerAccounts` est déjà
 *   uniformément scopé par personne (utilisateur, annonceur ou membre du
 *   personnel Wasplex partagent le même mécanisme).
 * - `risk_class = ordinary` — contrainte architecturale vérifiée par test
 *   (`DepositInitiationRouteTest`), pas une préférence esthétique :
 *   `GrantManager::activate()` refuse tout octroi `sensitive`/`critical`
 *   sans approbateur distinct, et `GrantAutoIssuer` (seul mécanisme
 *   d'octroi automatique à l'inscription) appelle toujours `activate()`
 *   avec un approbateur nul. Une capacité `self`, destinée à l'octroi
 *   automatique via `user.base`, ne peut donc jamais être `sensitive` ou
 *   `critical` — même contrainte, déjà respectée par `campaign.create`
 *   (migration `2026_07_25_100001`), auquel ce raisonnement s'aligne
 *   explicitement : un appel réel à un prestataire externe n'est pas
 *   anodin en soi, mais aucune valeur Ledger n'est engagée avant la
 *   confirmation externe (contrairement à `campaign.fund`, `critical`,
 *   jamais auto-octroyée), et la protection réelle contre un abus reste
 *   la vérification de signature HMAC du webhook, pas le niveau de risque
 *   de cette seule capacité d'initiation.
 * - `purpose_required = false` : comme toute action en libre-service déjà
 *   déclarée (`campaign.create`, `alert_case.submit`).
 * - `approval_required = false` : aucune revue humaine préalable ne
 *   remplacerait la preuve externe déjà exigée par le webhook.
 * - `minimum_session_assurance = weak` — volontairement distinct de
 *   `campaign.fund` (`strong`) : cette capacité ne crédite jamais
 *   directement une valeur Ledger, contrairement à `campaign.fund` qui
 *   comptabilise sur simple citation d'une preuve déjà vérifiée hors
 *   système. Ici, la seule garantie réelle contre un crédit indu est la
 *   vérification de signature HMAC du webhook GeniusPay
 *   (structurellement hors session utilisateur, une confirmation de mot
 *   de passe mid-session n'y changerait rien) et le rapprochement de
 *   montant (ecosystem/wallet/05 §3) — pas le niveau d'assurance de la
 *   session qui initie le paiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'wallet.deposit',
            'version' => 1,
            'domain' => 'wallet',
            'action' => 'deposit',
            'description' => "Initier un dépôt (recharge du Wallet propre) via GeniusPay — pilote Côte d'Ivoire (AMD-0017).",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'wallet.deposit')->delete();
    }
};
