<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dossier annonceur (ADR-0010 §3 « AdvertiserProfile » ;
 * `02-preuves-moderation-et-destinations.md` §1). Un annonceur agit
 * toujours via un représentant nominatif — jamais un compte Identity
 * partagé (ADR-0004 §3.2) : `representative_person_account_link_id`
 * référence une liaison personne-compte réelle, jamais un identifiant
 * générique « organisation ».
 *
 * Le dossier complet (bénéficiaires effectifs, preuves de propriété de
 * l'offre, droits sur contenus) n'est pas modélisé ici : ce noyau technique
 * ne construit ni preuve concrète ni file de modération (ADR-0010 §8) — ces
 * pièces vivront comme preuves attachées à un `ModerationCase` futur.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const STATUS_VALUES = ['active', 'suspended'];

    public function up(): void
    {
        Schema::create('advertising.advertiser_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('legal_name');
            $table->string('legal_identifier')->nullable();
            $table->string('country_code', 2);

            $table->foreignUuid('representative_person_account_link_id')
                ->constrained('identity.person_account_links')
                ->restrictOnDelete();

            $table->jsonb('licenses');
            $table->jsonb('territories');
            $table->string('status');

            $table->timestamps();

            $table->index('representative_person_account_link_id');
            $table->index('status');
        });

        $status = implode(',', array_map(fn (string $value): string => "'{$value}'", self::STATUS_VALUES));

        DB::statement(
            "ALTER TABLE advertising.advertiser_profiles ADD CONSTRAINT advertiser_profiles_status_check CHECK (status IN ({$status}))"
        );

        DB::statement(
            "ALTER TABLE advertising.advertiser_profiles ADD CONSTRAINT advertiser_profiles_country_code_format_check CHECK (country_code ~ '^[A-Z]{2}$')"
        );

        DB::statement(
            'ALTER TABLE advertising.advertiser_profiles ADD CONSTRAINT advertiser_profiles_licenses_is_array_check '
            ."CHECK (jsonb_typeof(licenses) = 'array')"
        );

        DB::statement(
            'ALTER TABLE advertising.advertiser_profiles ADD CONSTRAINT advertiser_profiles_territories_is_array_check '
            ."CHECK (jsonb_typeof(territories) = 'array')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.advertiser_profiles');
    }
};
