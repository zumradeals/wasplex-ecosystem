<?php

use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `event.self_submit` (W2, ferme la dette documentée sur
 * `event.submit`, migration `2026_07_25_100009` : « ce n'est pas encore le
 * vrai flux self-service utilisateur final ; ce dernier suppose un moteur
 * de prix versionné qui reste hors périmètre »). Ce moteur existe
 * désormais ({@see QualifiedEventPricingResolver}, ADR-0002).
 *
 * Couvre exclusivement la soumission par le bénéficiaire de sa propre
 * preuve d'attention qualifiée — jamais un tiers, jamais un montant
 * transmis par le client (calculé serveur via le registre Configuration).
 * `event.submit` (personnel Wasplex, portée large) reste inchangée et
 * distincte : un outil de dérogation anti-fraude pour les cas où la
 * soumission automatique ne convient pas, pas remplacée par celle-ci.
 *
 * Portée des grants réels — exclusivement `self`
 * (`ResourceContext.ownerPersonId` doit être la personne authentifiée
 * elle-même, c'est-à-dire le bénéficiaire réel de l'événement) : même
 * construction que `campaign.create`/`advertiser_profile.create`.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : crée une transaction Ledger de réservation réelle.
 * - `risk_class = ordinary`, PAS `sensitive` comme `event.submit` : cette
 *   capacité est auto-émise sans approbateur humain
 *   ({@see GrantAutoIssuer}),
 *   ce que `GrantManager::activate()` refuse pour `sensitive`/`critical`
 *   (approbateur distinct exigé). `event.submit` justifiait `sensitive` par
 *   sa portée large (n'importe quelle campagne, montant transmis par
 *   l'appelant) ; ici la portée est strictement `self` et le montant est
 *   calculé serveur (jamais transmis), même profil de risque que
 *   `campaign.create`/`advertiser_profile.create` (également `ordinary`,
 *   également auto-émis).
 * - `purpose_required = false` : une écriture, jamais une consultation.
 * - `approval_required = false` : décision métier entièrement portée par
 *   `CampaignBudgetService::submitQualifiedEvent()`.
 * - `minimum_session_assurance = weak` : même plancher que les autres
 *   capacités auto-émises de `user.base`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'event.self_submit',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'self_submit',
            'description' => 'Soumettre sa propre preuve d\'attention qualifiée, prix résolu par le registre Configuration (ADR-0002 ; 01-cycle-creation-valeur.md §3).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'event.self_submit')->delete();
    }
};
