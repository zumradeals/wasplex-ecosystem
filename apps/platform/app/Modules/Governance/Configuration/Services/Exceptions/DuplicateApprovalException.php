<?php

namespace App\Modules\Governance\Configuration\Services\Exceptions;

use RuntimeException;

/**
 * Un approbateur ne peut décider qu'une seule fois par `ValueVersion`
 * (ADR-0002 §8, `approvals_one_decision_per_approver`).
 */
class DuplicateApprovalException extends RuntimeException {}
