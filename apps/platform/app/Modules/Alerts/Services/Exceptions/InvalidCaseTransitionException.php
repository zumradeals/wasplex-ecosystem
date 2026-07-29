<?php

namespace App\Modules\Alerts\Services\Exceptions;

use RuntimeException;

/**
 * La transition demandée n'existe pas dans le graphe de la nature du
 * dossier (`CommunityCaseState`/`SosCaseState`/`DispatchState`).
 */
class InvalidCaseTransitionException extends RuntimeException {}
