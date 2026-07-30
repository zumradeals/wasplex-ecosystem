<?php

namespace App\Modules\Wallet\Deposit\Services\Exceptions;

use RuntimeException;

/**
 * Une transition d'état de dépôt demandée par le code applicatif ne
 * respecte pas la machine d'états d'ecosystem/wallet/05 §2. La garantie
 * ultime reste le déclencheur PostgreSQL (défense en profondeur) ; cette
 * exception permet un message métier clair avant d'atteindre la base.
 */
class InvalidDepositTransitionException extends RuntimeException {}
