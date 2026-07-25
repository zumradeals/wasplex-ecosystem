<?php

use App\Modules\Governance\Authorization\Services\GrantAutoIssuer;
use App\Modules\Identity\Services\SystemIdentity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Étend `person_account_links_origin_check` (P007) pour couvrir `system` —
 * la liaison portant le compte technique interne de Wasplex
 * ({@see SystemIdentity}), utilisé
 * exclusivement comme auteur de référence des octrois automatiques
 * ({@see GrantAutoIssuer}).
 *
 * Ne modifie jamais la contrainte déjà posée par la migration d'origine
 * (`2026_07_23_000004`, historique figé) : ajoute une nouvelle contrainte
 * distincte, comme toute évolution ultérieure d'un enum déjà fermé
 * (ADR-0004 §3.3 « compte technique » — décision explicite plutôt qu'un
 * paramètre versionné).
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const ORIGIN_VALUES = ['registration', 'migration', 'support_review', 'system'];

    public function up(): void
    {
        DB::statement('ALTER TABLE identity.person_account_links DROP CONSTRAINT person_account_links_origin_check');

        $origin = implode(',', array_map(
            fn (string $value): string => "'{$value}'",
            self::ORIGIN_VALUES,
        ));

        DB::statement(
            "ALTER TABLE identity.person_account_links ADD CONSTRAINT person_account_links_origin_check CHECK (origin IN ({$origin}))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE identity.person_account_links DROP CONSTRAINT person_account_links_origin_check');

        DB::statement(
            "ALTER TABLE identity.person_account_links ADD CONSTRAINT person_account_links_origin_check CHECK (origin IN ('registration','migration','support_review'))"
        );
    }
};
