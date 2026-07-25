<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use RuntimeException;

/**
 * L'auteur d'une `ValueVersion` ne peut jamais être son propre approbateur
 * ni son propre activateur, à aucun niveau C1-C3 (ADR-0002 §3.2, §3.3).
 */
class SelfApprovalRefusedException extends RuntimeException {}
