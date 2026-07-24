<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Une réservation ne peut jamais dépasser le disponible
 * (`02-cycle-financier-campagne.md` §4.1, ADR-0010 §7).
 */
class InsufficientBudgetException extends RuntimeException {}
