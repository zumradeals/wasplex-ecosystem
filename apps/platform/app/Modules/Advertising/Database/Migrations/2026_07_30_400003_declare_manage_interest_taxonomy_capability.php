<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertising.manage_interest_taxonomy` (véto du dirigeant,
 * 2026-07-30) : écran admin de gestion de la référence des centres
 * d'intérêt du profil publicitaire (`AdminInterestTaxonomyController`),
 * jusqu'ici seulement gérable via la commande
 * `advertising:manage-interest-taxonomy` (aucune ligne semée — le fondateur
 * choisit le contenu réel après déploiement, `ecosystem/publicite/
 * 01-cycle-creation-valeur.md` §5 ne citant "centres d'intérêt" que comme
 * exemple non normatif).
 *
 * Raisonnement des dimensions retenues :
 *
 * - `operation = write` : ajoute/retire une entrée réelle de la table de
 *   référence.
 * - `risk_class = ordinary` — contrairement aux capacités financières
 *   (`campaign.fund`, `wallet_deposit.manage_credentials`), cette écriture
 *   ne déplace aucune valeur et ne touche aucun secret : un libellé de
 *   centre d'intérêt erroné se corrige par une nouvelle écriture, sans
 *   risque de perte irréversible.
 * - Portée `resource_type` seule (jamais `self`) : réservée au personnel
 *   Wasplex habilité, comme `wallet_deposit.review`/`configuration.view` —
 *   jamais auto-octroyée à l'inscription.
 * - `purpose_required = false` / `approval_required = false` : gestion de
 *   contenu de référence, pas une décision individuelle sur une personne.
 * - `minimum_session_assurance = weak` : cohérent avec le niveau de risque
 *   ci-dessus (contrairement à `campaign.fund`/`wallet_deposit.manage_
 *   credentials`, `strong`, qui protègent un mouvement financier ou un
 *   secret).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertising.manage_interest_taxonomy',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'manage_interest_taxonomy',
            'description' => "Ajouter ou retirer une entrée de la référence des centres d'intérêt du profil publicitaire.",
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertising.manage_interest_taxonomy')->delete();
    }
};
