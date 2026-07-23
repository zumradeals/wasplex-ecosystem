<?php

namespace App\Modules\Governance\Authorization\Enums;

/**
 * Classe de risque d'une capacité (ADR-0004 §5). Plus le risque est élevé,
 * plus la preuve et la séparation des tâches exigées sont fortes.
 */
enum RiskClass: string
{
    case Ordinary = 'ordinary';
    case Sensitive = 'sensitive';
    case Critical = 'critical';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
