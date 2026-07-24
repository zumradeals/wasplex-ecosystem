<?php

namespace App\Modules\Advertising\Enums;

/**
 * Statut d'un dossier annonceur (`02-preuves-moderation-et-destinations.md`
 * §1). Un profil suspendu ne peut voir aucune de ses campagnes approuvées
 * (contrôle appliqué au niveau service, pas encore une capacité
 * Governance/Authorization — ADR-0010 §5).
 */
enum AdvertiserProfileStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
