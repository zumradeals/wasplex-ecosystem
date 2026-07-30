<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute `country_code`/`city`/`neighborhood` au profil publicitaire
 * (Lot 3 — ciblage réel, précision géographique demandée explicitement par
 * le fondateur en cours de lot : pays, ville, quartier). Aucune donnée
 * géographique par personne n'existait nulle part avant ce lot
 * (`identity.people` = `id` + timestamps uniquement, vérifié par audit) —
 * ajoutée ici, sur le profil déjà possédé par Advertising, jamais sur
 * `identity.people` (même frontière que âge/genre/intérêts, AMD-0009 §6),
 * et jamais par un accès cross-schema opportuniste (CLAUDE.md §6).
 *
 * `city`/`neighborhood` sont en texte libre (aucune taxonomie de référence
 * construite ici — hors périmètre, même choix que `territory` déjà en
 * texte libre) : la correspondance de ciblage se fait sur ces valeurs
 * telles que saisies, insensible à la casse. Un ciblage très précis
 * (quartier + âge + genre + intérêt) reste protégé par le mécanisme de
 * seuil minimal déjà existant (`AudienceSegmentGuard`, AMD-0009 §13) —
 * aucun garde-fou supplémentaire à inventer pour ce niveau de granularité.
 *
 * `country_code` : même forme que `territory.*`
 * (`regex:/^[A-Z]{2}$/`, déjà utilisé par `StoreCampaignRequest`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE advertising.person_advertising_profiles ADD COLUMN country_code text NULL');
        DB::statement(
            'ALTER TABLE advertising.person_advertising_profiles ADD CONSTRAINT person_advertising_profiles_country_code_check '
            ."CHECK (country_code IS NULL OR country_code ~ '^[A-Z]{2}$')"
        );
        DB::statement('ALTER TABLE advertising.person_advertising_profiles ADD COLUMN city text NULL');
        DB::statement('ALTER TABLE advertising.person_advertising_profiles ADD COLUMN neighborhood text NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE advertising.person_advertising_profiles DROP COLUMN IF EXISTS neighborhood');
        DB::statement('ALTER TABLE advertising.person_advertising_profiles DROP COLUMN IF EXISTS city');
        DB::statement('ALTER TABLE advertising.person_advertising_profiles DROP CONSTRAINT IF EXISTS person_advertising_profiles_country_code_check');
        DB::statement('ALTER TABLE advertising.person_advertising_profiles DROP COLUMN IF EXISTS country_code');
    }
};
