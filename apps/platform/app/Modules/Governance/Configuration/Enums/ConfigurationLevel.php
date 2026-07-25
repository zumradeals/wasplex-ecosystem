<?php

namespace App\Modules\Governance\Configuration\Enums;

/**
 * Niveau d'autorité d'une `Definition` (ADR-0002 §3). C0 volontairement
 * absent : un invariant constitutionnel n'est jamais une `Definition`
 * administrable, il est appliqué directement par le code (§3.1).
 */
enum ConfigurationLevel: string
{
    case C1 = 'c1';
    case C2 = 'c2';
    case C3 = 'c3';

    /**
     * Nombre d'approbateurs distincts requis avant activation (ADR-0002
     * §3.2 : « approbation par deux personnes... dont une indépendante de
     * l'auteur » pour C1 ; §3.3 : « un auteur et un approbateur distincts »
     * pour C2). C3 (« une approbation simple peut suffire », §3.4) exige
     * ici, par choix conservateur assumé, un approbateur distinct de
     * l'auteur — jamais une auto-activation, à aucun niveau (même principe
     * que `GrantManager::activate()`).
     */
    public function requiredApprovals(): int
    {
        return match ($this) {
            self::C1 => 2,
            self::C2, self::C3 => 1,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
