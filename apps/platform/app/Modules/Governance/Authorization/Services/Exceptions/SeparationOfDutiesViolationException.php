<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * Une capacité sensitive ou critical exige un approbateur distinct de
 * l'auteur (ADR-0004 §12).
 */
class SeparationOfDutiesViolationException extends RuntimeException {}
