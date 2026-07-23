<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma fonctionnel `governance`, conformément à ADR-0006 §3.
 *
 * Le rollback ne supprime jamais le schéma en cascade : les migrations
 * postérieures retirent déjà leurs tables dans l'ordre inverse avant que ce
 * rollback ne s'exécute. Une dépendance restante à ce stade signale une
 * incohérence et doit provoquer un échec visible, pas une suppression
 * transversale silencieuse (leçon retenue de la revue SIRR P003-A.2 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS governance');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS governance');
    }
};
