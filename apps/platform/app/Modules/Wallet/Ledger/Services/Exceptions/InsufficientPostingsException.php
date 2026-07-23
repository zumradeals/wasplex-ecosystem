<?php

namespace App\Modules\Wallet\Ledger\Services\Exceptions;

use RuntimeException;

/**
 * Une transaction comptable exige au moins deux postings
 * (architecture/05 "Règles structurelles").
 */
class InsufficientPostingsException extends RuntimeException {}
