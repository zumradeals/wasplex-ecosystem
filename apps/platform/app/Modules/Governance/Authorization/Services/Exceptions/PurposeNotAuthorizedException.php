<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * Une capacité marquée `purpose_required` ne peut être accordée sans une
 * finalité active et autorisée pour cette capacité (P003-B1 §6).
 */
class PurposeNotAuthorizedException extends RuntimeException {}
