<?php

namespace Database\Seeders;

use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Peuple `advertising.sector_classifications` et
 * `advertising.audience_segment_size_thresholds` avec des valeurs
 * DÉMONSTRATION (P007-W1) — pas une décision métier réelle.
 *
 * `01-classification-secteurs-et-contenus.md` §4 et ADR-0010 §3/§7 sont
 * explicites : la matrice pays × secteur et le seuil minimal de taille
 * d'audience sont des configurations versionnées sous ADR-0002, jamais
 * codées en dur ni devinées par une implémentation — aucun document
 * adopté ne fournit de valeur réelle pour l'un ou l'autre. Inventer ici
 * une vraie matrice ou un vrai seuil reviendrait à décider un paramètre
 * juridique/commercial (CLAUDE.md §7), ce que ce seeder ne fait pas.
 *
 * Volontairement un *seeder* (`php artisan db:seed --class=...`), jamais
 * une migration : `advertising.audience_segment_size_thresholds` n'admet
 * qu'une seule ligne `active` à la fois pour toute la plateforme (index
 * partiel `audience_segment_size_thresholds_one_active`) — une migration
 * (exécutée automatiquement par `RefreshDatabase` avant chaque suite de
 * tests) entrerait en collision avec toute donnée de test qui crée son
 * propre seuil actif (voir `AdvertisingTestCase::makeActiveSizeThreshold()`,
 * `tests/Feature/Modules/Advertising/AudienceSegmentTest.php`). Un seeder
 * n'est exécuté que sur demande explicite, jamais par la suite de tests.
 *
 * `country_code = 'ZZ'` (réservé ISO 3166-1, jamais un pays réel) et
 * `sector = 'demonstration_placeholder'` marquent délibérément la ligne de
 * classification comme non assignable à un pays ou un secteur réel.
 * `minimum_size = 1` n'a aucune signification métier : il sert uniquement
 * à ce qu'un seuil `active` existe pour que `AudienceSegmentGuard` ne
 * échoue pas fermé en développement.
 *
 * Ni l'une ni l'autre ne doivent jamais être lues comme une décision de
 * classification sectorielle ou de seuil antifraude réelle (AMD-0009 §13).
 * À retirer (ou remplacer, jamais muter) dès qu'une vraie valeur est
 * décidée et versionnée par les personnes habilitées (Koné/SIRR, via
 * ADR-0002).
 *
 * Peuple aussi (W2) une `Definition` DÉMONSTRATION de prix de base pour
 * `QualifiedEventPricingResolver` (`advertising.qualified_event_base_price`,
 * `value = 1`, la plus petite unité monétaire non nulle possible — jamais
 * un vrai prix décidé) : sans elle, aucune `CampaignVersion` créée par
 * l'écran de démonstration (P007-W1) ne pourrait résoudre de prix et donc
 * jamais accepter d'auto-soumission `event.self_submit`. Contrairement aux
 * deux données ci-dessus, celle-ci passe par le vrai cycle
 * `ConfigurationValueManager` (propose → soumission → approbation →
 * activation, avec deux comptes démonstration distincts comme auteur et
 * approbateur) : elle prouve que le mécanisme réel fonctionne, plutôt que
 * de contourner ses garanties de séparation des pouvoirs même pour une
 * donnée de démonstration.
 */
class AdvertisingDemoConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSectorClassification();
        $this->seedAudienceThreshold();
        $this->seedQualifiedEventBasePrice();
        $this->seedDailyAutoAcceptanceQuota();
    }

    private function seedSectorClassification(): void
    {
        if (! DB::table('advertising.sector_classifications')
            ->where('country_code', 'ZZ')
            ->where('sector', 'demonstration_placeholder')
            ->exists()) {
            DB::table('advertising.sector_classifications')->insert([
                'id' => (string) Str::uuid7(),
                'country_code' => 'ZZ',
                'sector' => 'demonstration_placeholder',
                'version' => 1,
                'sector_class' => 'standard_authorization',
                'minimum_age' => null,
                'required_evidence' => json_encode([], JSON_THROW_ON_ERROR),
                'warnings' => json_encode(
                    ['DÉMONSTRATION — aucune valeur réelle décidée ; ne pas utiliser en production (voir AdvertisingDemoConfigurationSeeder).'],
                    JSON_THROW_ON_ERROR
                ),
                'allowed_formats' => json_encode(['display'], JSON_THROW_ON_ERROR),
                'allowed_targeting' => json_encode(['broad'], JSON_THROW_ON_ERROR),
                'frequency_rules' => json_encode(['note' => 'demonstration_placeholder'], JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
                'review_level' => 'standard',
                'minimum_approvals' => 1,
                'state' => 'active',
                'effective_from' => now(),
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedAudienceThreshold(): void
    {
        if (! DB::table('advertising.audience_segment_size_thresholds')->where('state', 'active')->exists()) {
            $nextVersion = 1 + (int) DB::table('advertising.audience_segment_size_thresholds')->max('version');

            DB::table('advertising.audience_segment_size_thresholds')->insert([
                'id' => (string) Str::uuid7(),
                'version' => $nextVersion,
                'minimum_size' => 1,
                'state' => 'active',
                'effective_from' => now(),
                'effective_to' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedQualifiedEventBasePrice(): void
    {
        $stableKey = 'advertising.qualified_event_base_price';

        if (Definition::query()->where('stable_key', $stableKey)->where('state', DefinitionState::Active)->exists()) {
            return;
        }

        $definition = Definition::create([
            'stable_key' => $stableKey,
            'version' => 1,
            'domain' => 'advertising',
            'level' => ConfigurationLevel::C2,
            'value_type' => ValueType::Integer,
            'unit' => 'smallest_currency_unit',
            'constraints' => ['minimum' => 0],
            'description' => 'DÉMONSTRATION — prix de base d\'un événement publicitaire qualifié, sans coefficient de format/fréquence/rareté (TD-0006, noyau). Jamais un vrai prix décidé.',
            'state' => DefinitionState::Active,
        ]);

        $author = $this->demoLink('demo-pricing-author@wasplex.internal', 'Démonstration Auteur Tarification');
        $approver = $this->demoLink('demo-pricing-approver@wasplex.internal', 'Démonstration Approbateur Tarification');

        $manager = app(ConfigurationValueManager::class);

        $version = $manager->propose(
            $definition,
            1,
            'DÉMONSTRATION — aucune valeur réelle décidée ; ne pas utiliser en production (voir AdvertisingDemoConfigurationSeeder).',
            $author,
        );
        $version = $manager->submitForReview($version);
        $version = $manager->approve($version, $approver, 'Démonstration.');
        $manager->activate($version, $approver, (string) Str::uuid());
    }

    /**
     * Quota journalier d'acceptation automatique des auto-soumissions
     * (`QualifiedEventAutoAcceptancePolicy`, arbitrage Koné/SIRR
     * 2026-07-26) — valeur DÉMONSTRATION, jamais un vrai plafond décidé.
     * Sans configuration active, la politique échoue fermé : tout reste
     * en attente d'examen humain, et le ressenti « crédit immédiat » du
     * Feed de démonstration serait invisible. Même cycle réel
     * propose → approbation → activation que le prix de base ci-dessus.
     */
    private function seedDailyAutoAcceptanceQuota(): void
    {
        $stableKey = 'advertising.self_submission.daily_auto_acceptance_quota';

        if (Definition::query()->where('stable_key', $stableKey)->where('state', DefinitionState::Active)->exists()) {
            return;
        }

        $definition = Definition::create([
            'stable_key' => $stableKey,
            'version' => 1,
            'domain' => 'advertising',
            'level' => ConfigurationLevel::C2,
            'value_type' => ValueType::Integer,
            'unit' => 'qualified_events_per_beneficiary_per_day',
            'constraints' => ['minimum' => 0],
            'description' => 'DÉMONSTRATION — plafond journalier d\'événements qualifiés acceptés automatiquement par bénéficiaire (QualifiedEventAutoAcceptancePolicy). Jamais un vrai quota décidé.',
            'state' => DefinitionState::Active,
        ]);

        $author = $this->demoLink('demo-pricing-author@wasplex.internal', 'Démonstration Auteur Tarification');
        $approver = $this->demoLink('demo-pricing-approver@wasplex.internal', 'Démonstration Approbateur Tarification');

        $manager = app(ConfigurationValueManager::class);

        $version = $manager->propose(
            $definition,
            50,
            'DÉMONSTRATION — aucune valeur réelle décidée ; ne pas utiliser en production (voir AdvertisingDemoConfigurationSeeder).',
            $author,
        );
        $version = $manager->submitForReview($version);
        $version = $manager->approve($version, $approver, 'Démonstration.');
        $manager->activate($version, $approver, (string) Str::uuid());
    }

    private function demoLink(string $email, string $name): PersonAccountLink
    {
        $existingUser = DB::table('users')->where('email', $email)->first();

        if ($existingUser !== null) {
            return PersonAccountLink::query()->where('user_id', $existingUser->id)->firstOrFail();
        }

        $user = app(RegistersUserIdentity::class)->register([
            'name' => $name,
            'email' => $email,
            'password' => (string) Str::uuid(),
        ]);

        return PersonAccountLink::query()->where('user_id', $user->id)->firstOrFail();
    }
}
