<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `event.accept` (P005-F). Couvre exclusivement
 * `CampaignBudgetService::acceptQualifiedEvent()` (P005-A, déjà écrit et
 * testé) — validation d'un `QualifiedEvent` `Pending` : consomme la
 * réservation et répartit le net distribuable 50/50 (ADR-0010 §4 ligne 4
 * « validation », AMD-0002).
 *
 * Portée délibérément PAS `self` sur le bénéficiaire : un bénéficiaire ne
 * peut jamais valider sa propre preuve (`01-cycle-creation-valeur.md` §4
 * invariant 3-4 dépendent d'une preuve *acceptée*, jamais auto-acceptée).
 * `resource_type = advertising.qualified_event` sans `resource_ids` (même
 * forme que `campaign.moderate` sur `advertising.moderation_case`), tenue
 * par le même personnel anti-fraude/mesure d'attention que `event.submit`.
 *
 * Limite documentée, non résolue par ce lot (même nature que celle déjà
 * posée sur `campaign.fund`) : rien n'empêche techniquement qu'un grant
 * `event.accept` soit, par erreur, émis en `self` à un bénéficiaire — la
 * protection réelle reste une pratique d'émission de grants (ADR-0004
 * §13.4), pas une contrainte technique de ce lot.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : consomme la réservation et répartit une valeur
 *   réelle.
 * - `risk_class = critical` — même classe que `campaign.fund`, pour le
 *   même motif : c'est le moment exact où une valeur devient un droit
 *   utilisateur réellement payable (ADR-0003 §8 « événement qualifié »),
 *   sans aucune vérification technique intégrée au-delà de la décision de
 *   l'appelant. Contrairement à `event.submit`/`event.reject`
 *   (réversibles, sans création de valeur), cette action n'a pas
 *   d'équivalent « annuler proprement » dans ce lot (ADR-0003 §6 : une
 *   reprise n'est possible que par contre-écriture motivée, hors périmètre
 *   ici).
 * - `purpose_required = false` : une écriture, jamais une consultation ni
 *   un export sensible au sens d'ADR-0004 §8.
 * - `approval_required = false` : la décision métier reste entièrement
 *   portée par `CampaignBudgetService::acceptQualifiedEvent()`.
 * - `minimum_session_assurance = strong` — même palier que `campaign.fund`,
 *   pour le même motif (mouvement financier réel, `strong` réellement
 *   atteignable via P003-B4/TD-0002-B, contrairement à `Standard`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'event.accept',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'accept',
            'description' => 'Valider un QualifiedEvent pending et répartir le net distribuable 50/50 (ADR-0010 §4 ligne 4 ; AMD-0002).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'event.accept')->delete();
    }
};
