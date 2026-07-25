<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma fonctionnel `configuration` (W2, ADR-0002 §8 : « le registre est
 * administré dans Laravel et stocké dans PostgreSQL... chaque définition
 * possède un domaine métier responsable » — un schéma dédié, distinct de
 * `governance`, sur le modèle de `advertising`/`ledger`/`identity`).
 *
 * Le rollback ne supprime jamais le schéma en cascade : les migrations
 * postérieures retirent déjà leurs tables dans l'ordre inverse avant que ce
 * rollback ne s'exécute (même garde-fou que `create_governance_schema`,
 * revue SIRR P003-A.2 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS configuration');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS configuration');
    }
};
