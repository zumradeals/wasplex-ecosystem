<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Un code de centre d'intérêt qui ne correspond à aucune entrée active
 * d'`advertising.interest_taxonomy_entries` n'est jamais accepté
 * silencieusement (véto du dirigeant, 2026-07-30).
 */
class UnknownInterestCodeException extends RuntimeException {}
