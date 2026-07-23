<?php

namespace App\Modules\Wallet\Ledger\Services\Exceptions;

use RuntimeException;

/**
 * Pour une transaction comptabilisée, la somme des débits doit égaler
 * exactement la somme des crédits, par devise (ADR-0003 §1, §17).
 */
class UnbalancedTransactionException extends RuntimeException {}
