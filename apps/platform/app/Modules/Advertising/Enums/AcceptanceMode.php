<?php

namespace App\Modules\Advertising\Enums;

/**
 * Nature de la décision qui a accepté un QualifiedEvent (arbitrage
 * Koné/SIRR 2026-07-26) : `Automatic` — contrôles serveur déterministes
 * tous passés, règles épinglées par
 * `acceptance_rules_configuration_key`/`_version` ; `Manual` — décision
 * humaine via `event.accept` (cas suspects et dérogations). Null tant que
 * l'événement n'est pas accepté.
 */
enum AcceptanceMode: string
{
    case Automatic = 'automatic';
    case Manual = 'manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
