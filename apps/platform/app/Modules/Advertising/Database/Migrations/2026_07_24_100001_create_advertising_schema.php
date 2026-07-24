<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma fonctionnel `advertising` (ADR-0010 §2, architecture/12 : Publicité
 * — campagnes, diffusion, attention ; ne doit pas posséder de solde
 * utilisateur, voir §D « Comptes Ledger nécessaires » de P005-A).
 *
 * Le rollback ne supprime jamais le schéma en cascade, sur le modèle de
 * `governance` et `ledger` (P003-A.2 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS advertising');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS advertising');
    }
};
