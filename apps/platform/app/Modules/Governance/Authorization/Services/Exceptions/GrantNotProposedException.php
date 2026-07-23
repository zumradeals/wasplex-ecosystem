<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * Seul un grant au stade `proposed` peut être activé : aucune réactivation
 * et aucune activation répétée d'un grant déjà actif, suspendu, révoqué ou
 * expiré (P003-B1.3 §4).
 */
class GrantNotProposedException extends RuntimeException {}
