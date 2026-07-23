<?php

namespace App\Modules\Identity\Enums;

/**
 * Catégorie constitutionnelle d'une organisation enregistrable (Constitution, article 7).
 *
 * L'utilisateur individuel n'est jamais représenté par une organisation : il
 * est représenté par une personne et un compte. Aucun rôle « Agent » ne doit
 * jamais être ajouté à cette énumération.
 */
enum OrganizationCategory: string
{
    case Wasplex = 'wasplex';
    case Advertiser = 'advertiser';
    case Institution = 'institution';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
