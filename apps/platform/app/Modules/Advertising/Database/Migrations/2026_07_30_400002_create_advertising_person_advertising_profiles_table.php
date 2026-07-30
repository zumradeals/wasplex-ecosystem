<?php

use App\Modules\Advertising\Services\AdvertisingProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.person_advertising_profiles` (véto du dirigeant,
 * 2026-07-30 ; AMD-0009). Profil publicitaire facultatif et consenti —
 * tranche d'âge, genre (inclus sur véto explicite, absent des textes
 * adoptés), centres d'intérêt — collecté séparément d'`identity.people`,
 * qui exclut par construction documentée toute donnée de profil
 * publicitaire (P003-A §6, AMD-0009 §6 : « KYC, finance, santé [...] sont
 * séparés du profil publicitaire et ne l'enrichissent pas automatiquement »).
 *
 * `consent_version`/`consent_given_at`/`consent_withdrawn_at` : consentement
 * spécifique, versionné et révocable (AMD-0009 §4). Un retrait efface les
 * valeurs déjà saisies (voir `AdvertisingProfileService::withdrawConsent()`)
 * plutôt que de les garder inertes — minimisation réelle (AMD-0009 §2),
 * jamais un simple flag qui laisserait la donnée en base sans usage.
 *
 * `interests` : tableau de `code` d'`advertising.interest_taxonomy_entries`
 * actifs au moment de la saisie — validé côté service
 * ({@see AdvertisingProfileService}),
 * jamais par une contrainte SQL (la liste active change dans le temps).
 *
 * Hors périmètre de ce lot : la lecture par le ciblage réel
 * (`AudienceSegment`/`AudienceCriteria`) reste un lot séparé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.person_advertising_profiles (
                id uuid PRIMARY KEY,
                person_id uuid NOT NULL REFERENCES identity.people (id),
                age_bracket text NULL,
                gender text NULL,
                interests jsonb NOT NULL DEFAULT '[]',
                consent_version integer NULL,
                consent_given_at timestamptz NULL,
                consent_withdrawn_at timestamptz NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT person_advertising_profiles_person_id_unique UNIQUE (person_id),
                CONSTRAINT person_advertising_profiles_age_bracket_check CHECK (age_bracket IS NULL OR age_bracket IN ('18-24', '25-34', '35-44', '45-54', '55-64', '65+')),
                CONSTRAINT person_advertising_profiles_gender_check CHECK (gender IS NULL OR gender IN ('woman', 'man', 'other', 'prefer_not_to_say')),
                CONSTRAINT person_advertising_profiles_interests_is_array_check CHECK (jsonb_typeof(interests) = 'array')
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.person_advertising_profiles');
    }
};
