<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use RuntimeException;

/**
 * Aucune `Definition` active ne correspond à la clé stable demandée
 * (ADR-0002 §8).
 */
class DefinitionNotAvailableException extends RuntimeException {}
