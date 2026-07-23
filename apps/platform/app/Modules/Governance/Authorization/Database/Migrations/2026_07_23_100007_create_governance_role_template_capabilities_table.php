<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capacités proposées par un rôle modèle. Cette table ne confère elle-même
 * aucun droit : elle documente seulement une proposition (ADR-0004 §6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance.role_template_capabilities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('role_template_id')
                ->constrained('governance.role_templates')
                ->restrictOnDelete();
            $table->foreignUuid('capability_definition_id')
                ->constrained('governance.capability_definitions')
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['role_template_id', 'capability_definition_id'], 'role_template_capabilities_unique_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance.role_template_capabilities');
    }
};
