<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use RuntimeException;

/**
 * L'opération demandée exige que la `ValueVersion` soit dans un état
 * précis du cycle de vie (ADR-0002 §4, §8) ; l'état actuel ne le permet
 * pas.
 */
class ValueVersionNotInStateException extends RuntimeException {}
