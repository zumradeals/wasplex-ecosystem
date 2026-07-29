<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma fonctionnel `alerts` (P008-A, AMD-0007, architecture/02 §« Alertes
 * et Restitutions ») — module propriétaire de ses tables, sur le modèle de
 * `configuration`/`advertising`/`ledger`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS alerts');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS alerts');
    }
};
