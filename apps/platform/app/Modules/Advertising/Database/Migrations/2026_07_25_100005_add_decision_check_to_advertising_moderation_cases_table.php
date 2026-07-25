<?php

use App\Modules\Advertising\Enums\ModerationDecision;
use App\Modules\Advertising\Services\ModerationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `advertising.moderation_cases.decision` (P005-A) n'avait encore aucune
 * contrainte : ce lot (P005-D) est le premier à l'écrire depuis du code
 * applicatif ({@see ModerationService::recordDecision()}).
 * Ensemble fermé documenté par
 * {@see ModerationDecision}, sur le modèle
 * exact des contraintes `status`/`precautionary_measure` déjà posées par
 * `2026_07_24_100009_create_advertising_moderation_cases_table`. `NULL`
 * reste autorisé : un dossier encore `open` n'a pas de décision.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const DECISION_VALUES = ['violation_confirmed', 'no_violation_found'];

    public function up(): void
    {
        $decision = implode(',', array_map(fn (string $v): string => "'{$v}'", self::DECISION_VALUES));

        DB::statement(
            "ALTER TABLE advertising.moderation_cases ADD CONSTRAINT moderation_cases_decision_check CHECK (decision IS NULL OR decision IN ({$decision}))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE advertising.moderation_cases DROP CONSTRAINT moderation_cases_decision_check');
    }
};
