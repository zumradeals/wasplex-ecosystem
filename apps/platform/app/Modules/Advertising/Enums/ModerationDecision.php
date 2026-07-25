<?php

namespace App\Modules\Advertising\Enums;

/**
 * Décision d'un ModerationCase (ADR-0010 §3 : « motif, gravité, décision,
 * mesure conservatoire, recours » — dimension distincte de la mesure
 * conservatoire elle-même, {@see PrecautionaryMeasure}). Ensemble fermé à
 * deux valeurs, directement dérivé du vocabulaire de
 * `03-signalements-sanctions-et-remuneration.md` §1 : un signalement « ne
 * prouve pas seul la violation » — la décision qui clôt le dossier ne peut
 * donc que confirmer la violation signalée ou constater qu'elle n'est pas
 * établie. Aucune troisième valeur n'est devinée : `03-...md` §5 (sanctions
 * proportionnées) et le recours structuré restent hors périmètre de ce lot.
 */
enum ModerationDecision: string
{
    case ViolationConfirmed = 'violation_confirmed';
    case NoViolationFound = 'no_violation_found';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
