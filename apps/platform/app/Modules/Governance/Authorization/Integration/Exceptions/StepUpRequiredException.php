<?php

namespace App\Modules\Governance\Authorization\Integration\Exceptions;

/**
 * Décision `step_up_required` : une session plus forte pourrait suffire,
 * mais aucune élévation n'est jamais déclenchée automatiquement ici — le
 * module appelant reste seul responsable d'inviter l'utilisateur à
 * renforcer sa session (P003-B2 §C, TD-0001-B).
 */
class StepUpRequiredException extends AuthorizationOutcomeException {}
