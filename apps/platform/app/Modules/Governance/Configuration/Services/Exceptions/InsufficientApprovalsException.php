<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use RuntimeException;

/**
 * Le nombre d'approbations actives requis par le niveau de la `Definition`
 * (ADR-0002 §3.2-§3.4, {@see ConfigurationLevel::requiredApprovals()})
 * n'est pas encore atteint.
 */
class InsufficientApprovalsException extends RuntimeException {}
