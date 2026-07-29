<?php

namespace App\Modules\Alerts\Enums;

/**
 * État de revue d'une correspondance (ecosystem/alertes/02 §7) : le moteur
 * produit un candidat, jamais une décision finale — seule une revue humaine
 * valide ou rejette.
 */
enum CorrespondenceReviewState: string
{
    case Pending = 'pending';
    case Candidate = 'candidate';
    case Validated = 'validated';
    case Rejected = 'rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
