<?php

namespace App\Modules\Alerts\Enums;

/**
 * Niveau de vérification d'un dossier (mission P008-A §7.2). Un SOS créé
 * sans authentification complète démarre toujours `unverified` (AMD-0007
 * §2).
 */
enum VerificationLevel: string
{
    case Unverified = 'unverified';
    case Reviewed = 'reviewed';
    case Verified = 'verified';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
