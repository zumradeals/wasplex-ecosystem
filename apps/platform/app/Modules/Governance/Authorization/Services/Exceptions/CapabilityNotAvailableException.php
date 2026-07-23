<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * Une capacité inactive ne peut recevoir de grant actif (P003-B1 §12).
 */
class CapabilityNotAvailableException extends RuntimeException {}
