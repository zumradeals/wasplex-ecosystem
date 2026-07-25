<?php

use App\Modules\Governance\Authorization\Integration\SessionAssuranceResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.fund` (P005-E). Couvre exclusivement
 * `CampaignBudgetService::fund()` (P005-A, déjà écrit et testé depuis
 * `CampaignBudgetCycleTest`) — l'encaissement confirmé d'un annonceur qui
 * augmente le budget disponible d'une campagne (ADR-0010 §4 ligne 1 ;
 * ADR-0003 §7-8 « préfinancement »).
 *
 * Portée des grants réels — délibérément PAS `self` sur l'annonceur, à
 * l'inverse de `campaign.create` : ADR-0003 §7 exige un « encaissement
 * confirmé », ADR-0003 §14 range le financement du côté du service Ledger/
 * rapprochement, jamais d'une auto-déclaration par le module métier
 * lui-même, et ADR-0004 §13.2/§13.4 distingue explicitement les
 * représentants annonceurs (consultent budgets et résultats) des équipes
 * internes Wasplex — « finance » y est nommément une fonction interne
 * séparée. Un annonceur qui pourrait créditer son propre budget par simple
 * déclaration («&nbsp;j'ai payé&nbsp;») contournerait entièrement la
 * notion de preuve exigée par CLAUDE.md §2 (« Aucun crédit Wallet sans
 * financement, preuve... »). La portée retenue est donc
 * `resource_type = advertising.campaign` sans `resource_ids` (même forme
 * que `campaign.moderate` : couvre n'importe quelle campagne, jamais
 * scopée à un dossier annonceur précis) — tenue exclusivement par du
 * personnel finance Wasplex.
 *
 * Limite non résolue par ce lot, documentée plutôt que masquée : rien
 * n'empêche techniquement qu'un grant `campaign.fund` soit, par erreur,
 * émis avec `self` à un représentant annonceur — la protection réelle
 * reste, comme le prescrit ADR-0004 §13.4, une pratique d'émission de
 * grants (« les équipes internes reçoivent les capacités nécessaires à
 * leur fonction »), pas une contrainte technique de ce lot. Le
 * rapprochement externe réel (ADR-0003 §12 : webhook prestataire, relevé
 * bancaire) reste également hors périmètre : `funding_reference` est ici
 * une simple citation d'une preuve déjà vérifiée hors système par le
 * personnel finance, exactement comme le service `fund()` l'attendait déjà
 * depuis P005-A.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : crée une transaction Ledger équilibrée réelle.
 * - `risk_class = critical` — première capacité de ce niveau dans le
 *   module : contrairement à `campaign.approve`/`campaign.moderate`
 *   (`sensitive`), cette action injecte une valeur économique réelle dans
 *   le système sans aucune vérification technique intégrée au-delà de la
 *   référence citée par l'appelant (ADR-0003 §13, ratio de couverture,
 *   directement affecté par chaque financement). `GrantManager::activate()`
 *   traite aujourd'hui `sensitive`/`critical` de façon mécaniquement
 *   identique (approbateur distinct exigé) — mais `critical` reste le
 *   signal sémantique correct pour la revue a posteriori de SIRR
 *   (CLAUDE.md §5) et pour une politique de double contrôle plus stricte
 *   le jour où le moteur la distinguera réellement.
 * - `purpose_required = false` : une écriture, jamais une consultation ni
 *   un export sensible au sens d'ADR-0004 §8 — même raisonnement que
 *   toutes les capacités déjà déclarées par ce module.
 * - `approval_required = false` : aucune formule ni condition d'octroi
 *   générique ne remplace la vérification humaine du financement, qui a
 *   déjà eu lieu hors système avant l'appel (ADR-0003 §12).
 * - `minimum_session_assurance = strong` — première capacité de ce module
 *   à l'exiger. Contraste direct et volontaire avec le raisonnement déjà
 *   écrit sur `campaign.approve` (« approuver une campagne n'est ni un
 *   mouvement financier ni une action destructive » → `weak` suffisant) :
 *   `campaign.fund` EST exactement le mouvement financier que ce
 *   raisonnement excluait. `Strong` est réellement atteignable dès
 *   aujourd'hui (contrairement à `Standard`, TD-0002-A) via la
 *   reconfirmation de mot de passe mid-session déjà câblée par P003-B4
 *   ({@see SessionAssuranceResolver}) —
 *   aucune capacité morte créée ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.fund',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'fund',
            'description' => "Enregistrer un encaissement confirmé d'annonceur et créditer le budget disponible d'une campagne (ADR-0010 §4 ; ADR-0003 §7-8).",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.fund')->delete();
    }
};
