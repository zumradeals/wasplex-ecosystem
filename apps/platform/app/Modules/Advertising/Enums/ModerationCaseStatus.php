<?php

namespace App\Modules\Advertising\Enums;

/**
 * Statut d'un ModerationCase. Contrainte SQL déjà posée par P005-A
 * (`moderation_cases_status_check`, `open`/`resolved`) : ce lot (P005-D)
 * est le premier à faire transiter ce champ par du code applicatif — d'où
 * l'introduction de cet enum PHP maintenant, sur le modèle exact de
 * `CampaignVersionState`/`PrecautionaryMeasure`, plutôt qu'une chaîne
 * brute désormais écrite depuis un service.
 */
enum ModerationCaseStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
