<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `event.reject` (P005-F). Couvre exclusivement
 * `CampaignBudgetService::rejectQualifiedEvent()` (P005-A, déjà écrit et
 * testé) — contre-écriture explicite de la réservation d'origine
 * (ADR-0010 §4 ligne 5 « rejet/expiration » ; ADR-0003 §11).
 *
 * Portée : `resource_type = advertising.qualified_event` sans
 * `resource_ids`, mêmes tenants et même raisonnement que `event.accept` —
 * jamais `self` sur le bénéficiaire.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : contre-écriture réelle (débit réservé / crédit
 *   disponible).
 * - `risk_class = sensitive`, PAS `critical` : symétrique à `event.submit`
 *   — libère une réservation sans avoir jamais créé de valeur distribuable
 *   (`01-cycle-creation-valeur.md` §4 invariant 4 : « aucune rémunération
 *   sans accomplissement de la condition »). Aucun droit utilisateur n'a
 *   encore été créé à ce stade, contrairement à `event.accept`.
 * - `purpose_required = false` : une écriture, jamais une consultation ni
 *   un export sensible au sens d'ADR-0004 §8.
 * - `approval_required = false` : la décision métier reste entièrement
 *   portée par `CampaignBudgetService::rejectQualifiedEvent()`.
 * - `minimum_session_assurance = weak` : cohérent avec le risque
 *   `sensitive` ci-dessus — même palier que `event.submit`/
 *   `campaign.approve`/`campaign.moderate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'event.reject',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'reject',
            'description' => 'Refuser un QualifiedEvent pending par contre-écriture explicite de sa réservation (ADR-0010 §4 ligne 5 ; ADR-0003 §11).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'event.reject')->delete();
    }
};
