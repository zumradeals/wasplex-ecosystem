<?php

namespace App\Modules\Governance\Authorization\Support;

use RuntimeException;

/**
 * Conditions invalides, de version inconnue ou de format inconnu.
 *
 * Le moteur d'autorisation capture toujours cette exception pour produire un
 * refus explicite plutôt que de la laisser remonter comme une erreur
 * technique (P003-B1 §16).
 */
class InvalidConditionsPayloadException extends RuntimeException {}
