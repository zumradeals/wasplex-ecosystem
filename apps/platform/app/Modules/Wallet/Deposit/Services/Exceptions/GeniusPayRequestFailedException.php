<?php

namespace App\Modules\Wallet\Deposit\Services\Exceptions;

use RuntimeException;

/**
 * L'appel à GeniusPay a échoué (erreur réseau, code d'erreur documenté,
 * réponse malformée). Ne présume jamais un état de dépôt à partir de cette
 * exception : le dépôt reste en `draft`, aucun `checkout_url` n'existe.
 */
class GeniusPayRequestFailedException extends RuntimeException {}
