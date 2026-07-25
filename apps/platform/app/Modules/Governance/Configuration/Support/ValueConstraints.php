<?php

namespace App\Modules\Governance\Configuration\Support;

use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Services\Exceptions\InvalidConfigurationValueException;

/**
 * Validation d'une valeur proposée contre le type et les contraintes d'une
 * `Definition` (ADR-0002 §7.1 : « schéma de type, unité, plage et valeurs
 * autorisées »).
 *
 * Volontairement minimal (W2, noyau) : type, `minimum`/`maximum` (valeurs
 * numériques) et `enum` (valeurs autorisées). Aucune formule ni contrainte
 * exécutable libre (§3.6) — « contraintes entre paramètres » plus riches
 * (§7.1) restent différées à la Simulation, hors périmètre de ce lot.
 */
final class ValueConstraints
{
    /**
     * @param  array<string, mixed>  $constraints
     */
    public static function assertValid(ValueType $type, array $constraints, mixed $value): void
    {
        self::assertType($type, $value);

        if (array_key_exists('enum', $constraints)) {
            self::assertEnum($constraints['enum'], $value);

            return;
        }

        if (in_array($type, [ValueType::Integer, ValueType::Number], true)) {
            self::assertRange($constraints, $value);
        }
    }

    private static function assertType(ValueType $type, mixed $value): void
    {
        $valid = match ($type) {
            ValueType::Integer => is_int($value),
            ValueType::Number => is_int($value) || is_float($value),
            ValueType::String => is_string($value),
            ValueType::Boolean => is_bool($value),
        };

        if (! $valid) {
            throw new InvalidConfigurationValueException(
                "la valeur ne correspond pas au type déclaré par la definition : {$type->value}"
            );
        }
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private static function assertRange(array $constraints, mixed $value): void
    {
        if (array_key_exists('minimum', $constraints) && $value < $constraints['minimum']) {
            throw new InvalidConfigurationValueException("la valeur est inférieure au minimum autorisé ({$constraints['minimum']})");
        }

        if (array_key_exists('maximum', $constraints) && $value > $constraints['maximum']) {
            throw new InvalidConfigurationValueException("la valeur est supérieure au maximum autorisé ({$constraints['maximum']})");
        }
    }

    private static function assertEnum(mixed $enum, mixed $value): void
    {
        if (! is_array($enum) || ! in_array($value, $enum, true)) {
            throw new InvalidConfigurationValueException('la valeur ne fait pas partie des valeurs autorisées par la definition');
        }
    }
}
