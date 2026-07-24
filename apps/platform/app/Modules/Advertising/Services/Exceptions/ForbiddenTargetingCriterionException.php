<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Aucune caractéristique sensible n'est un critère de ciblage commercial
 * ordinaire (AMD-0009 §14).
 */
class ForbiddenTargetingCriterionException extends RuntimeException {}
