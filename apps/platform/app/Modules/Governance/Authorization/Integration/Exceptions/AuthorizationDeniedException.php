<?php

namespace App\Modules\Governance\Authorization\Integration\Exceptions;

/**
 * Décision `denied` : refus définitif, jamais transformable en autorisation.
 */
class AuthorizationDeniedException extends AuthorizationOutcomeException {}
