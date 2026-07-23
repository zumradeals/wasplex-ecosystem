<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organisation enregistrable : wasplex, advertiser ou institution
 * (Constitution article 7 ; P003-A §6).
 *
 * L'affiliation institutionnelle complète, les campagnes et les contrats
 * commerciaux ne sont pas construits ici (hors périmètre P003-A).
 *
 * Historique figé localement : les valeurs ci-dessous ne doivent jamais être
 * remplacées par une référence aux enums applicatifs
 * OrganizationCategory/OrganizationState, afin qu'une évolution future de
 * ces enums ne modifie pas le comportement de cette migration déjà exécutée
 * (revue SIRR P003-A.2 §3).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const CATEGORY_VALUES = ['wasplex', 'advertiser', 'institution'];

    /**
     * @var list<string>
     */
    private const STATE_VALUES = ['draft', 'active', 'suspended', 'closed'];

    public function up(): void
    {
        Schema::create('identity.organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('category');
            $table->string('legal_name');
            $table->string('display_name');
            $table->string('country_code', 2)->nullable();
            $table->string('state');
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_to')->nullable();
            $table->timestamps();

            $table->index('category');
        });

        $category = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            self::CATEGORY_VALUES,
        ));

        $state = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            self::STATE_VALUES,
        ));

        DB::statement(
            "ALTER TABLE identity.organizations ADD CONSTRAINT organizations_category_check CHECK (category IN ({$category}))"
        );

        DB::statement(
            "ALTER TABLE identity.organizations ADD CONSTRAINT organizations_state_check CHECK (state IN ({$state}))"
        );

        DB::statement(
            'ALTER TABLE identity.organizations ADD CONSTRAINT organizations_period_check CHECK (effective_to IS NULL OR effective_to > effective_from)'
        );

        // Le pays n'est jamais déduit d'une IP, d'un téléphone ou d'une devise
        // (ADR-0006 §7) : il doit être explicitement déclaré pour toute
        // organisation autre que Wasplex elle-même.
        DB::statement(
            "ALTER TABLE identity.organizations ADD CONSTRAINT organizations_country_required_check CHECK (category = 'wasplex' OR country_code IS NOT NULL)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.organizations');
    }
};
