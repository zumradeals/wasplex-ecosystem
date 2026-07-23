<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Schéma fonctionnel `identity`, conformément à ADR-0006 §3.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS identity');
    }

    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS identity CASCADE');
    }
};
