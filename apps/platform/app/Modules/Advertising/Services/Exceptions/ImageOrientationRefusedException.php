<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Image mesurée en paysage (largeur > hauteur) — Lot 6, instruction
 * explicite du fondateur 2026-07-30 : format vertical façon TikTok,
 * portrait ou carré acceptés, jamais un ratio exact imposé.
 */
class ImageOrientationRefusedException extends RuntimeException {}
