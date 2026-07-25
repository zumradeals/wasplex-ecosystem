<?php

namespace App\Modules\Governance\Configuration\Enums;

/**
 * Type primitif d'une valeur configurable (ADR-0002 §8). Aucun type
 * composite libre : « les calculs utilisent des modèles de formule
 * pré-approuvés, des paramètres typés » (§3.6) — un objet JSON n'est pas un
 * type autorisé ici, seulement des scalaires directement validables.
 */
enum ValueType: string
{
    case Integer = 'integer';
    case Number = 'number';
    case String = 'string';
    case Boolean = 'boolean';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
