<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare la première capacité réelle du module Publicité (ADR-0004 §11 :
 * « les modules déclarent leurs capacités »), sur le modèle exact de
 * TD-0003-A (Wallet) déjà cité par ADR-0010 §5. Aucun outil d'administration
 * générique n'est utilisé : cette insertion appartient au module
 * propriétaire, comme toute donnée de son propre domaine.
 *
 * `campaign.create` couvre exclusivement la création d'une Campaign et
 * d'une première CampaignVersion à l'état `draft` par son auteur — jamais
 * l'approbation (`campaign.approve`), la modération (`campaign.moderate`)
 * ni aucune diffusion, qui restent des capacités distinctes à déclarer par
 * des lots futurs (ADR-0010 §5).
 *
 * Raisonnement des dimensions retenues (P005-B, aucune valeur devinée) :
 *
 * - `operation = write` : la capacité crée une ressource, elle ne consulte
 *   ni n'exporte rien (Operation::Export ne s'applique pas).
 * - `risk_class = ordinary` : un draft n'engage encore aucun budget ni
 *   diffusion (ADR-0010 §4, §8 — le cycle financier ne commence qu'au
 *   financement puis à la réservation, jamais à la simple création d'une
 *   version en `draft`). Le risque réel (secteur, montant, audience) n'est
 *   d'ailleurs évalué qu'à l'approbation (ADR-0010 §5, `02-preuves-...md`
 *   §1 : « campagne à risque élevé »), pas à cette étape.
 * - `purpose_required = false` : ADR-0004 §8 réserve la finalité obligatoire
 *   à « toute consultation ou export sensible ». `campaign.create` ne
 *   consulte ni n'exporte une donnée d'autrui : l'annonceur crée sa propre
 *   campagne, dans le périmètre de son propre `AdvertiserProfile`, déjà
 *   vérifié par la portée du grant (`self`) — aucune finalité distincte
 *   n'ajoute de garantie supplémentaire à ce stade.
 * - `approval_required = false` : explicitement hors périmètre de cette
 *   capacité (ADR-0010 §5) — l'approbation d'une CampaignVersion reste un
 *   processus métier propre à `CampaignVersionService::approve()`, jamais
 *   une condition du moteur d'autorisation pour la simple création d'un
 *   draft.
 * - `minimum_session_assurance = weak` : la portée la plus étroite
 *   nécessaire est appliquée (ADR-0004 §7). La création d'un draft est
 *   réversible (aucun budget engagé, aucune diffusion, aucune donnée
 *   d'autrui exposée) ; elle ne présente donc pas le profil de risque
 *   (financier, destructif ou d'exposition) que P003-B4 réserve au palier
 *   `strong`. Exiger `strong` dès la création reviendrait à imposer une
 *   authentification renforcée à une action sans effet financier ni
 *   irréversible — disproportionné au regard d'ADR-0004 §9. Les capacités
 *   futures à effet réel (approbation, financement, diffusion) pourront
 *   exiger un palier plus élevé, décidé indépendamment pour chacune.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.create',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'create',
            'description' => "Créer une campagne publicitaire et sa première version à l'état draft (ADR-0010 §3, §5).",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.create')->delete();
    }
};
