<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Table `advertising.person_economic_type_assignments` (instruction
 * explicite du fondateur, 2026-07-31). Affectation courante d'une personne
 * à un type économique — une seule ligne par personne (pas d'historique
 * dans ce lot : un changement de type écrase l'affectation précédente,
 * jamais les événements déjà rémunérés selon l'ancien type, qui restent
 * inchangés par construction — voir docs/02 §6 « les événements passés ne
 * sont pas recalculés »). Une personne sans ligne ici reçoit le type par
 * défaut actif (`EconomicTypeResolver`).
 *
 * Rattachée à `identity.people` (pas `identity.person_account_links`) :
 * le type économique est un attribut de la personne, pas d'un compte
 * applicatif particulier — même échelle que `person_advertising_profiles`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE advertising.person_economic_type_assignments (
                id uuid PRIMARY KEY,
                person_id uuid NOT NULL UNIQUE REFERENCES identity.people (id),
                economic_type_id uuid NOT NULL REFERENCES advertising.economic_types (id),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX person_economic_type_assignments_economic_type_id_index ON advertising.person_economic_type_assignments (economic_type_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS advertising.person_economic_type_assignments');
    }
};
