<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `event.submit` (P005-F). Couvre exclusivement
 * `CampaignBudgetService::submitQualifiedEvent()` (P005-A, déjà écrit et
 * testé) — la réservation du coût maximal applicable et la création du
 * `QualifiedEvent` correspondant (ADR-0010 §4 ligne 3 « pendant contrôle »).
 *
 * Portée délibérément PAS `self` sur le bénéficiaire, décision tranchée
 * après un point d'arrêt explicite (P005-F, dossier de validation) : le
 * service exige `applied_price_amount` en paramètre direct sans jamais le
 * calculer lui-même (ADR-0010 §4/§8, aucune formule codée en dur) — le
 * prix devrait provenir d'une configuration de prix versionnée référencée
 * par `pricing_configuration_key`/`version` (ADR-0002), mais aucune table
 * ni service ne résout aujourd'hui cette référence en un montant. Exposer
 * cette route en libre-service au bénéficiaire lui permettrait de déclarer
 * lui-même son propre montant — fraude triviale, et coder une résolution
 * de prix ici inventerait la formule économique que CLAUDE.md §7 interdit
 * de trancher seul. La portée retenue est donc `resource_type =
 * advertising.campaign` sans `resource_ids` (même forme que
 * `campaign.fund`), tenue par du personnel Wasplex anti-fraude/mesure
 * d'attention qui cite un montant déjà vérifié hors système — exactement
 * le même compromis que `campaign.fund` pour le financement. Ce n'est
 * PAS encore le vrai flux self-service utilisateur final ; ce dernier
 * suppose un moteur de prix versionné qui reste hors périmètre de ce lot.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : crée une transaction Ledger de réservation réelle.
 * - `risk_class = sensitive`, PAS `critical` : contrairement à
 *   `campaign.fund`/`event.accept`, une réservation ne crée aucune valeur
 *   nouvelle ni ne la rend payable — elle déplace `available` vers
 *   `reserved` au sein d'un budget déjà financé, intégralement réversible
 *   par `event.reject` sans effet résiduel (même classe de risque que
 *   `campaign.approve`/`campaign.moderate`).
 * - `purpose_required = false` : une écriture, jamais une consultation ni
 *   un export sensible au sens d'ADR-0004 §8.
 * - `approval_required = false` : la décision métier reste entièrement
 *   portée par `CampaignBudgetService::submitQualifiedEvent()`.
 * - `minimum_session_assurance = weak` : cohérent avec le risque `sensitive`
 *   ci-dessus (réversible, sans création de valeur) — même palier que
 *   `campaign.approve`/`campaign.moderate`, à la différence de
 *   `campaign.fund`/`event.accept` qui, eux, créent réellement de la
 *   valeur distribuable.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'event.submit',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'submit',
            'description' => 'Réserver le coût applicable et créer un QualifiedEvent (ADR-0010 §4 ligne 3 ; 01-cycle-creation-valeur.md §3).',
            'operation' => 'write',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'event.submit')->delete();
    }
};
