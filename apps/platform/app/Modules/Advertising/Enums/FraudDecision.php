<?php

namespace App\Modules\Advertising\Enums;

/**
 * Décision anti-fraude attachée à un QualifiedEvent (AMD-0010 §10 : «
 * anomalie, suspicion faible, suspicion sérieuse, fraude confirmée »),
 * jamais un score binaire. Ce module ne construit aucune méthode de calcul
 * (ADR-0010 §8, hors périmètre P005-A) : seule la valeur existe, posée par
 * un appelant externe.
 */
enum FraudDecision: string
{
    case None = 'none';
    case Anomaly = 'anomaly';
    case WeakSuspicion = 'weak_suspicion';
    case SeriousSuspicion = 'serious_suspicion';
    case ConfirmedFraud = 'confirmed_fraud';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
