<?php

use App\Modules\Advertising\Services\AudienceCriteria;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AudienceSegment : critères autorisés + estimation agrégée (ADR-0010 §3).
 * Ne stocke jamais d'identité individuelle. `criteria` ne peut porter
 * aucune des clés sensibles interdites par AMD-0009 §14 — vérifié par
 * {@see AudienceCriteria} avant
 * écriture, jamais par un accès direct à `identity` (ADR-0010 §3).
 *
 * `estimated_size` est l'estimation brute interne ; elle n'est jamais
 * exposée telle quelle sous le seuil minimal configuré — voir
 * {@see AudienceSegmentGuard} et
 * `below_threshold_at_creation`, qui trace la décision prise (jamais
 * recalculée après coup) sans être elle-même la donnée retournée à
 * l'annonceur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising.audience_segments', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->foreignUuid('campaign_version_id')
                ->constrained('advertising.campaign_versions')
                ->restrictOnDelete();

            $table->jsonb('criteria');
            $table->unsignedInteger('estimated_size');

            $table->foreignUuid('size_threshold_id')
                ->constrained('advertising.audience_segment_size_thresholds')
                ->restrictOnDelete();
            $table->boolean('below_threshold_at_creation');

            $table->timestamps();

            $table->unique('campaign_version_id');
        });

        DB::statement(
            'ALTER TABLE advertising.audience_segments ADD CONSTRAINT audience_segments_criteria_is_object_check '
            ."CHECK (jsonb_typeof(criteria) = 'object')"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising.audience_segments');
    }
};
