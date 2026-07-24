<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Une campagne à risque élevé exige une validation humaine indépendante de
 * son créateur (ADR-0010 §5, `02-preuves-moderation-et-destinations.md` §1).
 */
class IndependentApproverRequiredException extends RuntimeException {}
