<?php

namespace App\Modules\Advertising\Services\Exceptions;

use RuntimeException;

/**
 * Le fichier stocké n'est pas une image réellement décodable
 * (`getimagesize()` a échoué) — jamais le mimetype déclaré par le client
 * seul (Lot 6, même discipline que `VideoDurationUnreadableException`).
 */
class ImageUnreadableException extends RuntimeException {}
