<?php

namespace App\Modules\Wallet\Ledger\Services\Exceptions;

use RuntimeException;

/**
 * Un posting ne peut jamais avoir un montant nul ou négatif (ADR-0003 §15,
 * contrainte `postings_amount_positive_check`).
 */
class NonPositiveAmountException extends RuntimeException {}
