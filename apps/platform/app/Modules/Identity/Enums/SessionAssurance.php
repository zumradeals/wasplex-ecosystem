<?php

namespace App\Modules\Identity\Enums;

/**
 * Force de la session d'authentification courante.
 *
 * Cet axe appartient au contexte de session : il n'est jamais persisté comme un
 * niveau KYC permanent aux côtés des autres axes d'assurance du compte (cf. P003-A §6).
 * Aucune valeur autre que Weak ne doit être retenue sans événement probant établi
 * pendant la session en cours (facteur récent, appareil approuvé, etc.).
 */
enum SessionAssurance: string
{
    case Weak = 'weak';
    case Standard = 'standard';
    case Strong = 'strong';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
