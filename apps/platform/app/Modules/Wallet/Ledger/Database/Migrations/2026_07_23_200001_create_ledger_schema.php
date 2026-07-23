<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma fonctionnel `ledger`, conformément à architecture/12 (Wallet et
 * Ledger : comptes, postings, paiements).
 *
 * Le rollback ne supprime jamais le schéma en cascade : les migrations
 * postérieures retirent déjà leurs tables dans l'ordre inverse avant que ce
 * rollback ne s'exécute, sur le modèle du schéma `governance` (P003-A.2 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS ledger');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS ledger');
    }
};
