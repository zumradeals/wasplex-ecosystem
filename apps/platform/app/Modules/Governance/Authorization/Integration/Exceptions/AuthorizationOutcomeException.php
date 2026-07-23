<?php

namespace App\Modules\Governance\Authorization\Integration\Exceptions;

use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use RuntimeException;

/**
 * Base commune des trois issues non permissives du moteur, rattachées à
 * leur {@see AuthorizationResult} complet (P003-B2 §C). Le résultat reste
 * disponible pour le module appelant ; sa restitution à un client HTTP doit
 * toujours passer par un adaptateur qui n'expose ni grant, ni politique, ni
 * détail interne.
 */
abstract class AuthorizationOutcomeException extends RuntimeException
{
    public function __construct(
        public readonly AuthorizationResult $result,
    ) {
        parent::__construct($result->reason->explanation);
    }
}
