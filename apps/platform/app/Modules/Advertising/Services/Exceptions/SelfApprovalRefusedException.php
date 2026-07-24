<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * L'auteur d'une campagne ne peut jamais être son propre approbateur
 * (ADR-0010 §5, même matrice que `GrantManager::activate()` — TD-0001-A).
 */
class SelfApprovalRefusedException extends RuntimeException {}
