<?php

namespace App\Modules\Alerts\Services\Exceptions;

use RuntimeException;

/**
 * Aucune organisation affiliée active n'est éligible pour cette catégorie
 * et ce territoire (ecosystem/alertes/03 §1.1) — Wasplex n'invente jamais
 * un routage de complaisance.
 */
class NoEligibleInstitutionException extends RuntimeException {}
