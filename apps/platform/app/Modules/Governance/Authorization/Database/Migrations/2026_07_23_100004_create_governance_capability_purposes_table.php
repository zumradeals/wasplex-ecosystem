<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finalités autorisées pour une capacité donnée (ADR-0004 §8). Une capacité
 * marquée `purpose_required` ne peut jamais être accordée pour une finalité
 * absente de cette liaison.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance.capability_purposes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('capability_definition_id')
                ->constrained('governance.capability_definitions')
                ->restrictOnDelete();
            $table->foreignUuid('purpose_definition_id')
                ->constrained('governance.purpose_definitions')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['capability_definition_id', 'purpose_definition_id'], 'capability_purposes_unique_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance.capability_purposes');
    }
};
