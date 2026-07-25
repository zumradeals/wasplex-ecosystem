<?php

namespace Database\Seeders;

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
 */
class AdvertisingDemoConfigurationSeeder extends Seeder
{
    public function run(): void
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
}
