<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.economic_types` (instruction explicite du fondateur,
 * 2026-07-31 ; docs/02-abonnements-et-types-utilisateurs.md §3). Trois
 * types économiques gouvernent la part utilisateur réelle d'un événement
 * publicitaire — `user_share_percentage` module la part utilisateur
 * standard (50 % du montant net distribuable, AMD-0002, inchangée) :
 * un type à 100 % reçoit exactement cette moitié, un type à 60 % en reçoit
 * 60 %, le reliquat non versé au bénéficiaire restant chez Wasplex (le
 * partage global 50/50 annonceur↔Wasplex n'est jamais dépassé — voir
 * `CampaignBudgetService::acceptQualifiedEvent()`).
 *
 * `stable_key` est un identifiant technique interne, jamais affiché — `name`
 * est le seul nom public, entièrement administrable (docs/02 §3 : « le nom
 * du type ne doit jamais être codé comme une règle technique »).
 *
 * Un seul type peut être `is_default = true` ET `state = active`
 * simultanément (index partiel ci-dessous) : c'est le type appliqué à toute
 * personne sans affectation explicite
 * (voir `advertising.person_economic_type_assignments`).
 *
 * Cycle de vie repris de `ConfigurationState` (draft/active/retired), même
 * gabarit que `SectorClassification`/`AudienceSegmentSizeThreshold`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.economic_types (
                id uuid PRIMARY KEY,
                stable_key text NOT NULL,
                name text NOT NULL,
                version integer NOT NULL,
                user_share_percentage integer NOT NULL CHECK (user_share_percentage BETWEEN 0 AND 100),
                monthly_quota integer NULL CHECK (monthly_quota IS NULL OR monthly_quota > 0),
                is_default boolean NOT NULL DEFAULT false,
                state text NOT NULL,
                effective_from timestamptz NOT NULL DEFAULT now(),
                effective_to timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT economic_types_state_check CHECK (state IN ('draft', 'active', 'retired')),
                CONSTRAINT economic_types_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)
            )
        SQL);

        // Une seule paire (stable_key, version) — mirroir exact de
        // `sector_classifications_unique_active` mais sans la dimension
        // pays : les types économiques ne sont pas régionalisés dans ce lot.
        DB::statement('CREATE UNIQUE INDEX economic_types_stable_key_version_unique ON advertising.economic_types (stable_key, version)');

        // Un seul type actif par stable_key à la fois.
        DB::statement("CREATE UNIQUE INDEX economic_types_one_active_per_key ON advertising.economic_types (stable_key) WHERE state = 'active'");

        // Un seul type par défaut actif pour toute la plateforme — même
        // gabarit que `audience_segment_size_thresholds_one_active`
        // (migration `2026_07_24_100004`).
        DB::statement("CREATE UNIQUE INDEX economic_types_one_active_default ON advertising.economic_types (state) WHERE is_default = true AND state = 'active'");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.economic_types');
    }
};
