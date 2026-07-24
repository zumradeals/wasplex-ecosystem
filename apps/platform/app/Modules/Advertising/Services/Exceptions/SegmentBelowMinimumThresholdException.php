<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Un segment sous le seuil minimal configuré est refusé, jamais retourné
 * tel quel (AMD-0009 §13, ADR-0010 §3, §7).
 */
class SegmentBelowMinimumThresholdException extends RuntimeException {}
