<?php

namespace App\Modules\Governance\Configuration\Enums;

/**
 * Cycle de vie d'une `Definition` (ADR-0002 §8), sur le modèle exact de
 * `App\Modules\Governance\Authorization\Enums\CapabilityState`.
 */
enum DefinitionState: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Retired = 'retired';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
