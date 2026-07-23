<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * Une politique inactive ne peut gouverner un nouveau grant (P003-B1 §12).
 */
class PolicyNotAvailableException extends RuntimeException {}
