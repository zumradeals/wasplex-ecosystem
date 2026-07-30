<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * `ffprobe` n'a pas pu déterminer la durée du fichier uploadé (fichier
 * corrompu ou non reconnu comme vidéo) — jamais un succès inventé.
 */
class VideoDurationUnreadableException extends RuntimeException {}
