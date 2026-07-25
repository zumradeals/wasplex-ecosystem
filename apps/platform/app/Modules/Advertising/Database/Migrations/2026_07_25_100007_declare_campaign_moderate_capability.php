<?php

use App\Modules\Governance\Authorization\Services\GrantManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `campaign.moderate` (P005-D), citée en exemple par ADR-0010 §5.
 * Couvre exclusivement la décision rendue sur un `ModerationCase` déjà
 * ouvert, avec application éventuelle d'une mesure conservatoire — jamais
 * l'ouverture du dossier elle-même (`campaign.report`, capacité distincte).
 *
 * Portée des grants réels : `resource_type = advertising.moderation_case`
 * sans `resource_ids` (même raisonnement que `campaign.report` : un
 * modérateur doit pouvoir trancher n'importe quel dossier, jamais seulement
 * ceux touchant son propre dossier annonceur — l'inverse d'un scope `self`).
 *
 * Séparation signaleur/modérateur (P005-D §3.B) : ni `03-...md` ni ADR-0004
 * §12 (qui liste explicitement les combinaisons interdites : « créer et
 * approuver une campagne sensible », etc., mais jamais « signaler et
 * modérer son propre signalement ») n'exigent qu'un signaleur ne puisse
 * jamais trancher son propre signalement. Ce lot n'invente donc PAS cette
 * séparation — mais le risque de conflit d'intérêts est réel (un
 * concurrent pourrait signaler puis, s'il détient aussi un grant
 * `campaign.moderate`, classer son propre signalement sans confirmer la
 * violation) et reste documenté comme risque ouvert au dossier de
 * validation P005-D plutôt que tranché silencieusement (CLAUDE.md §7).
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : transition d'état d'un ModerationCase déjà
 *   ouvert, éventuellement propagée à `Campaign.state`.
 * - `risk_class = sensitive` : une décision `campaign_suspended` a un effet
 *   réel et immédiat sur l'annonceur (blocage de toute nouvelle réservation,
 *   ADR-0010 §4 dernière ligne) — même classification que `campaign.approve`
 *   pour le même motif (effet réel, quasi-irréversible avant résolution).
 *   `RiskClass::Sensitive` a déjà un effet mécanique existant : activer un
 *   grant sensitive/critical exige un approbateur distinct du sujet et de
 *   l'auteur de la proposition ({@see GrantManager::activate()},
 *   P003-B3, TD-0001-A) — défense en profondeur à l'octroi du pouvoir de
 *   modérer, distincte de (et insuffisante seule pour) la séparation
 *   signaleur/modérateur documentée ci-dessus.
 * - `purpose_required = false` : une décision de modération n'est ni une
 *   consultation ni un export sensible au sens d'ADR-0004 §8.
 * - `approval_required = false` : la décision métier reste entièrement
 *   portée par `ModerationService::recordDecision()`/`applyPrecautionaryMeasure()`,
 *   jamais une condition du moteur générique.
 * - `minimum_session_assurance = weak` : même plancher que `campaign.approve`
 *   pour la même raison documentée là (TD-0002-A, `standard` structurellement
 *   inatteignable ; `strong` disproportionné hors mouvement financier ou
 *   action destructive directe).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'campaign.moderate',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'moderate',
            'description' => 'Trancher un ModerationCase ouvert, avec mesure conservatoire éventuelle (`03-signalements-sanctions-et-remuneration.md` §2 ; ADR-0010 §3).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'campaign.moderate')->delete();
    }
};
