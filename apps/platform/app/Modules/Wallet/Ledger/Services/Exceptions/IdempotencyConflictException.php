<?php

namespace App\Modules\Wallet\Ledger\Services\Exceptions;

use RuntimeException;

/**
 * La même clé d'idempotence est déjà associée à un contenu différent : la
 * demande est rejetée plutôt que rejouée (ADR-0003 §10).
 */
class IdempotencyConflictException extends RuntimeException {}
