<?php

use App\Modules\Advertising\Services\AdvertiserOnboardingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Déclare `advertiser_profile.create` (P007) — première capacité qui
 * gouverne la création d'un `AdvertiserProfile` par soi-même. Couvre
 * exclusivement {@see AdvertiserOnboardingService::registerAdvertiser()} :
 * une personne déclare son propre dossier annonceur, avec elle-même comme
 * représentant — jamais un dossier pour un tiers.
 *
 * Portée des grants réels — exclusivement `self`
 * (`ResourceContext.ownerPersonId` doit être la personne authentifiée
 * elle-même) : même raisonnement que `campaign.create` (migration
 * `2026_07_25_100001`), transposé à l'étape précédente (créer le dossier
 * annonceur lui-même, pas encore une campagne).
 *
 * Dette assumée, cohérente avec TD-0004-D (dossier annonceur minimal) : ce
 * dossier est créé directement à l'état `active`, sans vérification KYC ni
 * revue humaine — `02-preuves-moderation-et-destinations.md` §1 exige ces
 * preuves avant *diffusion*, pas avant la simple création d'un dossier ;
 * aucune campagne créée derrière ce dossier ne peut de toute façon être
 * approuvée ou financée sans capacités distinctes (`campaign.approve`,
 * `campaign.fund`), réservées au personnel Wasplex.
 *
 * Raisonnement des dimensions retenues (aucune valeur devinée) :
 *
 * - `operation = write` : crée une ressource.
 * - `risk_class = ordinary` : même niveau que `campaign.create` — aucun
 *   budget, aucune diffusion, aucune donnée d'autrui.
 * - `purpose_required = false` : une personne déclare son propre dossier.
 * - `approval_required = false` : décision métier entièrement portée par
 *   `AdvertiserOnboardingService`.
 * - `minimum_session_assurance = weak` : action réversible sans effet
 *   financier ni irréversible (un dossier peut être suspendu par le
 *   personnel Wasplex si nécessaire) — même plancher que toutes les
 *   capacités déclarées jusqu'ici (TD-0002-A).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('governance.capability_definitions')->insert([
            'id' => (string) Str::uuid7(),
            'stable_key' => 'advertiser_profile.create',
            'version' => 1,
            'domain' => 'advertising',
            'action' => 'create',
            'description' => 'Créer son propre dossier annonceur, avec soi-même comme représentant (ADR-0010 §3).',
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
        DB::table('governance.capability_definitions')->where('stable_key', 'advertiser_profile.create')->delete();
    }
};
