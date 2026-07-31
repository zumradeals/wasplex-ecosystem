<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Une personne a déjà atteint le nombre maximal de revisionnages
 * gratuits (quotidien ou total) pour cette `CampaignVersion` précise —
 * instruction explicite du fondateur, 2026-07-31.
 */
class FrequencyCapExceededException extends RuntimeException {}
