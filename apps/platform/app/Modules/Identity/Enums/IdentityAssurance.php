<?php

namespace App\Modules\Identity\Enums;

/**
 * Axe de preuve d'identité déclarée puis vérifiée. Cf. AMD-0010 et ecosystem/identite/01-niveaux-et-preuves.md.
 * Ne constitue jamais, seul, une autorisation d'opération (ADR-0004).
 */
enum IdentityAssurance: string
{
    case Undeclared = 'undeclared';
    case Declared = 'declared';
    case Verified = 'verified';
    case Reinforced = 'reinforced';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
