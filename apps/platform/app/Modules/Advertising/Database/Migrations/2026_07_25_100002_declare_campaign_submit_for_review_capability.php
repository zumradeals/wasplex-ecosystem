<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.submit_for_review` (P005-C), sur le modèle exact de
 * `declare_campaign_create_capability` (P005-B).
 *
 * Couvre exclusivement la transition `draft` → `in_review` d'une
 * CampaignVersion existante par son auteur (ou toute personne dont le grant
 * couvre le dossier annonceur concerné) — jamais l'approbation
 * (`campaign.approve`) ni la modération (`campaign.moderate`).
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : transition d'état d'une ressource déjà créée.
 * - `risk_class = ordinary` : même raisonnement que `campaign.create`
 *   (P005-B) — une version encore `in_review` n'engage aucun budget et ne
 *   diffuse rien (ADR-0010 §4, §8). Le risque réel n'est évalué qu'à
 *   l'approbation.
 * - `purpose_required = false` : aucune consultation ni export d'une donnée
 *   d'autrui (ADR-0004 §8) — l'annonceur soumet sa propre version, dans le
 *   périmètre de son propre dossier, déjà vérifié par la portée du grant.
 * - `approval_required = false` : ce champ gouverne l'exercice de la
 *   capacité elle-même par le moteur (`AuthorizationEngine::evaluate()`,
 *   retour systématique `ApprovalRequired` sur tout grant qualifiant) — un
 *   sens radicalement différent de « cette version nécessite une
 *   approbation métier », qui reste entièrement porté par
 *   `CampaignVersionService`/`campaign.approve`. Le fixer à `true` ici
 *   interdirait à quiconque de jamais soumettre une version en revue.
 * - `minimum_session_assurance = weak` : action réversible, sans effet
 *   financier ni irréversible (une version en `in_review` peut encore
 *   redevenir `draft` — voir le mécanisme de refus de P005-C) — même
 *   proportionnalité qu'ADR-0004 §9 appliquée à `campaign.create`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.submit_for_review',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'submit_for_review',
            'description' => "Soumettre une CampaignVersion à l'état draft vers l'état in_review (ADR-0010 §3, §5).",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.submit_for_review')->delete();
    }
};
