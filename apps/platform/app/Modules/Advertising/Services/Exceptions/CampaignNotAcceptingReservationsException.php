<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Une campagne suspendue ne peut plus engager de nouvelle réservation de
 * budget ; les réservations déjà engagées suivent leur cycle jusqu'à
 * résolution (ADR-0010 §4, §7).
 */
class CampaignNotAcceptingReservationsException extends RuntimeException {}
