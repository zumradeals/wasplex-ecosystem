<?php

namespace App\Modules\Governance\Authorization\Integration\Exceptions;

/**
 * Décision `approval_required` : une approbation distincte reste due, sans
 * qu'aucune opération irréversible ne soit exécutée automatiquement à sa
 * place — P003-B1 ne possède pas encore de preuve d'approbation, et cette
 * mission n'en construit pas (P003-B2 §C, TD-0001-B).
 */
class ApprovalRequiredException extends AuthorizationOutcomeException {}
