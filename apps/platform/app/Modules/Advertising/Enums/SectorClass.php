<?php

namespace App\Modules\Advertising\Enums;

/**
 * Classe d'un secteur publicitaire (ecosystem/publicite/01-classification-secteurs-et-contenus.md §2).
 * Classe A (interdit) n'est jamais activable administrativement (§1 :
 * « un paramètre administratif ne peut pas rendre licite ce que la
 * Constitution interdit ») — une ligne `Forbidden` ne peut donc jamais
 * porter l'état `active` d'une classification par ailleurs autorisante ;
 * elle documente au contraire l'interdiction elle-même.
 */
enum SectorClass: string
{
    case Forbidden = 'forbidden';
    case EnhancedAuthorization = 'enhanced_authorization';
    case StandardAuthorization = 'standard_authorization';
    case InstitutionalInformation = 'institutional_information';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
