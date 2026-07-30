<?php

use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.interest_taxonomy_entries` (véto du dirigeant,
 * 2026-07-30 : profil publicitaire — préalable au ciblage réel). Référence
 * des centres d'intérêt utilisables par le profil publicitaire
 * ({@see PersonAdvertisingProfile}), sur le
 * modèle exact de `advertising.sector_classifications` : pas d'écran admin
 * dédié dans ce lot, gérée via la commande
 * `advertising:manage-interest-taxonomy`.
 *
 * Aucune ligne n'est semée par cette migration : `ecosystem/publicite/
 * 01-cycle-creation-valeur.md` §5 ne cite « centres d'intérêt » que comme
 * exemple non normatif, sans liste concrète adoptée — inventer des valeurs
 * ici reviendrait à décider une donnée métier à la place du fondateur
 * (CLAUDE.md §7). Le contenu réel est ajouté après déploiement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.interest_taxonomy_entries (
                id uuid PRIMARY KEY,
                code text NOT NULL,
                label text NOT NULL,
                state text NOT NULL DEFAULT 'active',
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT interest_taxonomy_entries_code_unique UNIQUE (code),
                CONSTRAINT interest_taxonomy_entries_state_check CHECK (state IN ('active', 'retired'))
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.interest_taxonomy_entries');
    }
};
