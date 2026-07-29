<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `access.view` (P0-Admin, destination « Accès » de UX-0001 §8) —
 * consultation du registre des `Grant` détenus par toute personne, jusqu'ici
 * sans aucune route ni capacité de lecture dédiée (seule
 * `hasActiveGrant()`/`hasActiveStaffGrant()` existait, une vérification
 * ponctuelle, jamais une liste). Couvre exclusivement la consultation —
 * jamais la proposition, l'activation, la suspension ni la révocation d'un
 * grant (capacités distinctes, non déclarées par ce lot).
 *
 * Portée des grants réels — délibérément PAS `self` : consulter qui détient
 * quelle capacité, dans tout le système, n'a pas de notion de propriétaire
 * individuel (même raisonnement que `configuration.view`,
 * `2026_07_29_100001` côté Configuration). La portée retenue est donc
 * `resource_type = governance.grant` sans `resource_ids` — même forme que
 * `campaign.moderate`/`configuration.view` — tenue exclusivement par du
 * personnel Wasplex habilité, jamais par un rôle générique.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = read` : aucune écriture, même raisonnement que
 *   `campaign.view`/`wallet.view`/`configuration.view`.
 * - `risk_class = ordinary` : consulter qui détient quelle capacité n'a
 *   aucun effet ; même raisonnement que `configuration.view` — c'est
 *   l'opération et son effet qui gouvernent `risk_class`, pas la largeur
 *   de la portée. La donnée exposée (titulaire, capacité, fenêtre de
 *   validité, auteur/approbateur, motif de révocation) est déjà,
 *   individuellement, la même nature de donnée que celle déjà visible par
 *   `campaign.fund`/`campaign.moderate` (des `person_account_link_id`
 *   d'acteurs internes et de sujets déjà connus du système), jamais une
 *   donnée personnelle restreinte au sens d'ADR-0004 §8.
 * - `purpose_required = false` : consultation interne d'un registre
 *   d'habilitations, pas une donnée personnelle nécessitant une finalité
 *   déclarée.
 * - `approval_required = false` : simple lecture.
 * - `minimum_session_assurance = weak` : même plancher que `campaign.view`/
 *   `wallet.view`/`configuration.view` — aucun mouvement financier ni
 *   action destructive. Choix documenté au sens de la « porte de reprise »
 *   commune de TD-0001/TD-0002 (« activation d'un espace administrateur...
 *   en production ») : ce déclencheur s'est déjà produit avec le portail
 *   admin (PR #34), sans qu'aucune capacité déclarée depuis n'ait exigé
 *   `standard` — TD-0002-A (aucun signal `standard` fiable depuis une
 *   session HTTP réelle) reste donc non chargé par cette capacité, comme
 *   par toutes celles déclarées jusqu'ici. Une réévaluation plus large de
 *   TD-0001/TD-0002 resterait nécessaire le jour où une capacité de ce
 *   portail exigerait réellement `standard` — pas le cas ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'access.view',
            'version' => 1,
            'domain' => 'governance',
            'action' => 'view',
            'description' => 'Consulter les Grant détenus par toute personne : capacité, portée, fenêtre de validité, auteur/approbateur, révocation (ADR-0004 §5, §22).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'access.view')->delete();
    }
};
