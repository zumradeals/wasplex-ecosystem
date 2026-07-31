<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Table `advertising.frequency_cap_bounds` (instruction explicite du
 * fondateur, 2026-07-31 : « récompensé une seule fois pour une pub déjà
 * vue... peut revoir gratuitement au maximum 3 fois par jour », 10 au
 * total confirmé ensuite). Mirroir exact de
 * `advertising.video_ad_duration_bounds` — une seule ligne `active` à la
 * fois, versionnée, réglable par une seule personne habilitée. Même choix
 * délibéré que documenté sur cette table sœur : le module
 * Governance/Configuration générique exige un auteur et un approbateur
 * distincts sans dérogation, inutilisable seul pour un réglage
 * opérationnel simple.
 *
 * Cette migration insère directement la ligne active
 * `daily_free_view_limit=3, lifetime_free_view_limit=10` — valeurs
 * réelles confirmées par le fondateur, jamais une valeur démonstration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.frequency_cap_bounds (
                id uuid PRIMARY KEY,
                daily_free_view_limit integer NOT NULL CHECK (daily_free_view_limit > 0),
                lifetime_free_view_limit integer NOT NULL CHECK (lifetime_free_view_limit >= daily_free_view_limit),
                version integer NOT NULL CHECK (version > 0),
                state text NOT NULL,
                effective_from timestamptz NOT NULL,
                effective_to timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT frequency_cap_bounds_state_check CHECK (state IN ('active', 'retired'))
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX frequency_cap_bounds_one_active ON advertising.frequency_cap_bounds (state) WHERE state = \'active\'');

        DB::table('advertising.frequency_cap_bounds')->insert([
            'id' => (string) Str::uuid7(),
            'daily_free_view_limit' => 3,
            'lifetime_free_view_limit' => 10,
            'version' => 1,
            'state' => 'active',
            'effective_from' => now(),
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.frequency_cap_bounds');
    }
};
