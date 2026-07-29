<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `configuration.view` (P0-Admin, destination « Configurations »
 * de UX-0001 §8) — première capacité déclarée sur le registre central de
 * configuration (ADR-0002), jusqu'ici sans aucune route ni capacité
 * (TD-0006-F). Couvre exclusivement la consultation des `Definition` et de
 * l'historique de leurs `ValueVersion`/`Approval`/`Activation` — jamais la
 * proposition, l'approbation ou l'activation d'une valeur (capacités
 * distinctes, non déclarées par ce lot, cf. TD-0006-F).
 *
 * Portée des grants réels — délibérément PAS `self` : ce registre n'a pas
 * de notion de propriétaire individuel comme `campaign.view`
 * (`2026_07_25_100013`) ; une `Definition` appartient à un `domain` entier
 * (ex. « advertising », « wallet »), jamais à une personne. La portée
 * retenue est donc `resource_type = governance.configuration_definition`
 * sans `resource_ids` — même forme que `campaign.moderate` — tenue
 * exclusivement par du personnel Wasplex habilité à consulter la
 * configuration métier, jamais par un rôle générique.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = read` : aucune écriture, même raisonnement que
 *   `campaign.view`/`wallet.view`.
 * - `risk_class = ordinary` : consulter la configuration active et son
 *   historique d'approbation n'a aucun effet et n'expose aucune donnée
 *   personnelle au-delà des `person_account_link_id` déjà auteurs/
 *   approbateurs des décisions elles-mêmes (mêmes personnes que celles
 *   visibles sur `campaign.fund`/`campaign.approve`, déjà `ordinary`/
 *   `sensitive` selon l'effet, jamais élevées par la seule largeur de la
 *   portée). Une portée système entière ne rend pas, à elle seule, une
 *   capacité de LECTURE plus risquée qu'une capacité `self` : c'est
 *   l'opération et son effet qui gouvernent `risk_class` ici, pas la
 *   largeur du grant — cohérent avec `campaign.view` (self, ordinary) et
 *   `campaign.moderate` (système entier, mais `sensitive` à cause de son
 *   effet réel en écriture, pas de sa portée).
 * - `purpose_required = false` : consultation interne d'un registre de
 *   configuration métier, pas une donnée personnelle nécessitant une
 *   finalité déclarée (ADR-0004 §8).
 * - `approval_required = false` : simple lecture, aucune formule ni
 *   condition d'octroi générique ne s'y substitue.
 * - `minimum_session_assurance = weak` : même plancher que `campaign.view`/
 *   `wallet.view` — aucun mouvement financier ni action destructive ; évite
 *   sciemment `standard`, qui resterait une capacité morte tant que
 *   TD-0002-A (signal `standard` fiable) reste ouvert.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'configuration.view',
            'version' => 1,
            'domain' => 'governance',
            'action' => 'view',
            'description' => "Consulter les Definitions du registre central de configuration et l'historique de leurs ValueVersion/Approval/Activation (ADR-0002 §8).",
            'operation' => 'read',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'configuration.view')->delete();
    }
};
