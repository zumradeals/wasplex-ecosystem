<?php

namespace App\Modules\Alerts\Enums;

/**
 * Nature d'un dossier `alerts.cases` (P008-A, ecosystem/alertes/02 §1).
 * Deux machines d'états disjointes, jamais mélangées pour un même dossier
 * (voir le déclencheur `alerts.enforce_case_state_machine`).
 */
enum CaseNature: string
{
    case Community = 'community';
    case Sos = 'sos';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
