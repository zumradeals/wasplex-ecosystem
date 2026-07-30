<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * La durée réelle (mesurée par `ffprobe`, jamais déclarée par le client)
 * d'une vidéo uploadée est hors des bornes actives
 * (Lot 4, instruction explicite du fondateur 2026-07-30).
 */
class VideoDurationOutOfBoundsException extends RuntimeException {}
