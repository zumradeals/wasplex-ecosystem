<?php

namespace App\Modules\Identity\Enums;

use App\Modules\Identity\Services\SystemIdentity;

/**
 * Origine de la liaison personne-compte, conservée conformément à ADR-0006 §5 et §17.
 */
enum LinkOrigin: string
{
    case Registration = 'registration';
    case Migration = 'migration';
    case SupportReview = 'support_review';

    /**
     * Liaison portant le compte technique interne de Wasplex
     * ({@see SystemIdentity}), jamais une
     * personne réelle. N'authentifie jamais de session interactive ; sert
     * uniquement d'auteur de référence pour les octrois automatiques
     * (P007, ADR-0004 §3.3 « compte technique »).
     */
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
