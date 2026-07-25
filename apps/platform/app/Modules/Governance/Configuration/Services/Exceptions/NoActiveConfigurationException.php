<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use RuntimeException;

/**
 * Aucune `ValueVersion` active n'existe pour cette `Definition` : la
 * résolution échoue fermée plutôt que de retourner une valeur par défaut
 * silencieuse (ADR-0002 §5, §7.3 — « une opération financière dont la
 * règle ne peut être résolue de façon certaine échoue fermée »).
 */
class NoActiveConfigurationException extends RuntimeException {}
