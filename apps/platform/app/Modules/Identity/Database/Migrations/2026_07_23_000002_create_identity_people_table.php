<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une personne physique, distincte de tout compte (P003-A §6, ADR-0006 §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity.people', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity.people');
    }
};
