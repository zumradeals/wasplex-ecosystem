<?php

use App\Modules\Advertising\Models\AudienceSegmentSizeThreshold;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Table `advertising.video_ad_duration_bounds` (Lot 4, instruction
 * explicite du fondateur 2026-07-30 : « une vidéo de 30 à 60 secondes »,
 * durée modifiable depuis l'espace admin). Mirroir exact de
 * `advertising.audience_segment_size_thresholds`
 * ({@see AudienceSegmentSizeThreshold}) —
 * une seule ligne `active` à la fois, versionnée, réglable par une seule
 * personne. Choix délibéré plutôt que le module Governance/Configuration
 * générique : `ConfigurationValueManager` exige un auteur et un
 * approbateur distincts sans dérogation pour l'Administrateur Système
 * (contrairement à `GrantManager`, ADR-0004) — inutilisable seul.
 * Inventer une dérogation reviendrait à modifier un invariant déjà adopté
 * (ADR-0002) ; ce mirroir n'y touche pas.
 *
 * Cette migration insère directement la ligne active `min_seconds=30,
 * max_seconds=60` — valeur réelle donnée par le fondateur, jamais une
 * valeur DÉMONSTRATION (contrairement à `AdvertisingDemoConfigurationSeeder`,
 * qui ne sème que des placeholders explicitement non réels).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.video_ad_duration_bounds (
                id uuid PRIMARY KEY,
                min_seconds integer NOT NULL CHECK (min_seconds > 0),
                max_seconds integer NOT NULL CHECK (max_seconds >= min_seconds),
                version integer NOT NULL CHECK (version > 0),
                state text NOT NULL,
                effective_from timestamptz NOT NULL,
                effective_to timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT video_ad_duration_bounds_state_check CHECK (state IN ('active', 'retired'))
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX video_ad_duration_bounds_one_active ON advertising.video_ad_duration_bounds (state) WHERE state = \'active\'');

        DB::table('advertising.video_ad_duration_bounds')->insert([
            'id' => (string) Str::uuid7(),
            'min_seconds' => 30,
            'max_seconds' => 60,
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
        DB::statement('DROP TABLE IF EXISTS advertising.video_ad_duration_bounds');
    }
};
