<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Seule une version à l'état `draft` ou `in_review` peut être approuvée
 * (ADR-0010 §3).
 */
class CampaignVersionNotApprovableException extends RuntimeException {}
