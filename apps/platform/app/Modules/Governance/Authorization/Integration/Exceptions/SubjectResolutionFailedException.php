<?php

namespace App\Modules\Governance\Authorization\Integration\Exceptions;

use RuntimeException;

/**
 * Le sujet authentifié n'a pas pu être résolu avec confiance (P003-B2 §A).
 *
 * Un seul type d'exception couvre toutes les causes (absence de compte,
 * liaison inactive, appartenance non active ou non liée à ce compte,
 * organisation inactive) : le code d'appel ne doit jamais distinguer ces cas
 * pour décider d'un comportement plus permissif, seul le refus est possible.
 * Le code interne reste disponible pour l'audit et les tests, jamais pour
 * une réponse HTTP détaillée.
 */
class SubjectResolutionFailedException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
