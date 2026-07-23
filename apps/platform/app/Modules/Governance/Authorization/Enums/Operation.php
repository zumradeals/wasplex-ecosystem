<?php

namespace App\Modules\Governance\Authorization\Enums;

/**
 * Opération demandée sur une ressource. Un export exige une capacité conçue
 * explicitement pour l'export : une lecture simple ne suffit jamais (P003-B1 §16).
 */
enum Operation: string
{
    case Read = 'read';
    case Write = 'write';
    case Export = 'export';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
