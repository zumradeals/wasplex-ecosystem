<?php

use App\Modules\Governance\Authorization\Integration\SessionAssuranceResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.approve` (P005-C), sur le modèle exact de
 * `declare_campaign_create_capability` (P005-B).
 *
 * Couvre exclusivement la transition d'une CampaignVersion vers `approved`.
 * La séparation des tâches (ADR-0004 §12 : « créer et approuver une
 * campagne sensible » est une combinaison interdite ; ADR-0010 §5 :
 * « l'auteur d'une campagne ne peut jamais être son propre approbateur pour
 * une campagne à risque élevé ») n'est PAS exprimée ici par une condition du
 * moteur : `ConditionsMatcher` (P003-B1 §11) ne compare que des attributs du
 * sujet (assurance de contact/identité/unicité/session, statut
 * d'organisation) à des seuils déclarés — il n'a et n'a jamais eu de moyen
 * de comparer un attribut du sujet à un attribut de la ressource visée
 * (ici, `author_person_account_link_id` d'une CampaignVersion précise). Lui
 * ajouter cette capacité serait inventer un nouveau mécanisme plutôt que
 * réutiliser l'existant. La décision reste donc, comme le prescrit
 * ADR-0004 §11 (« le module propriétaire de la ressource conserve la
 * décision métier finale »), entièrement portée par
 * `CampaignVersionService::approve()` (P005-A, déjà écrit et testé) : c'est
 * elle qui refuse inconditionnellement `approver === author` (renforcé par
 * la contrainte SQL `campaign_versions_author_not_approver_check`) et exige
 * un approbateur non nul lorsque
 * `SectorClassification::requiresIndependentApprover()` (review_level
 * Enhanced ou minimum_approvals >= 2) est vraie.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : transition d'état d'une ressource déjà créée.
 * - `risk_class = sensitive` : approuver rend une version potentiellement
 *   diffusable (ADR-0010 §2 : « ne diffuse rien sans version approuvée
 *   immuable ») — contrairement à `campaign.create`/`submit_for_review`,
 *   c'est un effet réel, pas réversible par une simple resoumission
 *   (immuabilité sémantique dès `approved`). `RiskClass::Sensitive` a un
 *   effet mécanique direct et déjà existant :
 *   `GrantManager::activate()` (P003-B3, TD-0001-A) exige déjà un
 *   approbateur distinct pour ACTIVER un grant `sensitive`/`critical` —
 *   c'est-à-dire que le simple fait d'habiliter quelqu'un à approuver des
 *   campagnes exige déjà une séparation des tâches à l'attribution du
 *   pouvoir, en plus (jamais à la place) de celle appliquée par
 *   `CampaignVersionService` à chaque exercice réel de ce pouvoir. Défense
 *   en profondeur à deux niveaux distincts, aucun nouveau mécanisme.
 * - `purpose_required = false` : même raisonnement que `campaign.create` —
 *   l'approbateur agit dans le périmètre du dossier annonceur déjà vérifié
 *   par la portée du grant, aucune finalité distincte n'ajoute de garantie
 *   supplémentaire ici.
 * - `approval_required = false` : même raisonnement que
 *   `campaign.submit_for_review` — ce champ gouvernerait l'octroi de la
 *   capacité elle-même par le moteur générique, jamais la décision métier
 *   d'approbation d'une CampaignVersion précise, qui reste entièrement
 *   portée par `CampaignVersionService`.
 * - `minimum_session_assurance = weak` : une action à effet réel et
 *   quasi irréversible mériterait, en pure proportionnalité (ADR-0004 §9),
 *   davantage que le plancher `weak`. Mais `Standard` n'est aujourd'hui
 *   JAMAIS produit par le système : {@see SessionAssuranceResolver}
 *   documente de façon exhaustive (TD-0002-A) que ni le pipeline de login
 *   2FA de Fortify, ni celui des passkeys, ne persistent le moindre signal
 *   distinguant un login à second facteur vérifié d'un login par mot de
 *   passe seul — `fromRequest()` ne retourne jamais
 *   `SessionAssurance::Standard`. Exiger `standard` ici rendrait
 *   `campaign.approve` structurellement inatteignable par quiconque, pour
 *   toujours (aucun grant, aussi correctement attribué soit-il, ne
 *   pourrait jamais satisfaire cette condition) — un refus fermé permanent
 *   n'est pas une garantie de sécurité, c'est une capacité morte. `strong`
 *   (reconfirmation mot de passe mid-session, P003-B4) resterait
 *   disproportionné : approuver une campagne n'est ni un mouvement
 *   financier ni une action destructive. En attendant que TD-0002-A soit
 *   résolue par un lot dédié à l'authentification, seul `weak` est un
 *   palier réellement atteignable ; la séparation des tâches réelle reste
 *   de toute façon portée ailleurs (risk_class sensitive à l'octroi du
 *   grant, CampaignVersionService à l'exercice), jamais par ce champ.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.approve',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'approve',
            'description' => "Approuver une CampaignVersion à l'état in_review (ADR-0010 §3, §5).",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.approve')->delete();
    }
};
