<?php

namespace App\Modules\Wallet\Ledger\Services\Exceptions;

use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use RuntimeException;

/**
 * {@see LedgerPoster::post()} ne
 * comptabilise jamais une contre-écriture : seule
 * {@see LedgerPoster::reverse()} peut lier
 * une transaction à l'originale qu'elle corrige (ADR-0003 §11).
 */
class DirectReversalRefusedException extends RuntimeException {}
