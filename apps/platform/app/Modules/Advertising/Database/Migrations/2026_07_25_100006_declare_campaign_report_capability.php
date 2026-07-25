<?php

use App\Modules\Governance\Authorization\Services\GrantManager;
use App\Modules\Governance\Authorization\Support\ScopeMatcher;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.report` (P005-D), sur le modèle exact des déclarations
 * précédentes (P005-B/C). Couvre exclusivement l'ouverture d'un
 * `ModerationCase` par un signaleur — jamais la décision qui en résulte
 * (`campaign.moderate`, capacité distincte déclarée séparément).
 *
 * Portée volontairement large (P005-D §3.A) : `03-signalements-sanctions-et-remuneration.md`
 * §1 dit explicitement « Tout utilisateur peut signaler une campagne » —
 * n'importe laquelle, jamais seulement celles de son propre dossier
 * annonceur. Un grant `campaign.report` n'est donc jamais scopé `self`
 * (qui exigerait `ResourceContext.ownerPersonId === subject`, donc que le
 * signaleur soit lui-même l'annonceur visé — l'inverse exact du cas
 * d'usage réel, {@see ScopeMatcher}
 * ligne 20-24) : la portée retenue par les grants réels de cette capacité
 * est `resource_type = advertising.campaign` sans `resource_ids`, seule
 * dimension de {@see ScopePayload}
 * qui reste une restriction explicite (jamais un joker interdit par
 * `assertNotWildcard`) tout en couvrant N'IMPORTE QUELLE campagne. Ceci ne
 * réintroduit pas une capacité universelle interdite (CLAUDE.md §2, ADR-0004
 * §6) : le moteur d'autorisation continue d'exiger un `Grant` `Active`
 * individuellement proposé et activé pour CE sujet précis
 * ({@see GrantManager}) — ce
 * lot ne construit aucun mécanisme d'octroi automatique à « tout compte
 * créé » (dette documentée au dossier de validation P005-D : la
 * distribution large du grant lui-même, au sens opérationnel, reste hors
 * périmètre, cf. §7 CLAUDE.md « ajouter... une dépendance structurante non
 * décidée »).
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : la capacité crée une ressource (ModerationCase),
 *   elle ne consulte ni n'exporte rien.
 * - `risk_class = ordinary` : un signalement « ne prouve pas seul la
 *   violation » et ne déclenche aucune mesure automatique (§1) — aucun
 *   effet réel sur la campagne visée à ce stade, contrairement à
 *   `campaign.moderate` qui peut la suspendre.
 * - `purpose_required = false` : même raisonnement que `campaign.create` —
 *   une écriture, pas une consultation ni un export sensible (ADR-0004 §8).
 * - `approval_required = false` : la décision métier reste entièrement
 *   portée par `ModerationService::openCase()`, jamais une condition du
 *   moteur générique.
 * - `minimum_session_assurance = weak` : même plancher que toutes les
 *   capacités déclarées jusqu'ici (TD-0002-A, `standard` structurellement
 *   inatteignable). Un signalement, largement disponible et sans effet
 *   automatique, ne justifierait de toute façon pas `strong`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.report',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'report',
            'description' => 'Signaler une campagne (`03-signalements-sanctions-et-remuneration.md` §1). Ne prouve pas seul la violation.',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.report')->delete();
    }
};
