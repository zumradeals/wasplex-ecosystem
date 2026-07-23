<?php

namespace App\Modules\Wallet\Ledger\Services\Exceptions;

use RuntimeException;

/**
 * Une transaction ne s'équilibre que dans une seule devise ; ses postings ne
 * mélangent jamais deux devises (ADR-0003 §5).
 */
class CurrencyMismatchException extends RuntimeException {}
