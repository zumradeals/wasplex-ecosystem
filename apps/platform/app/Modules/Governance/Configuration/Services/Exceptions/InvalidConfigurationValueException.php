<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use RuntimeException;

/**
 * La valeur proposée ne respecte pas le type ou les contraintes déclarées
 * par la `Definition` (ADR-0002 §7.1). Aucune formule ni contrainte
 * exécutable libre n'est jamais acceptée (§3.6) : seuls type, plage et
 * énumération autorisée sont vérifiés.
 */
class InvalidConfigurationValueException extends RuntimeException {}
