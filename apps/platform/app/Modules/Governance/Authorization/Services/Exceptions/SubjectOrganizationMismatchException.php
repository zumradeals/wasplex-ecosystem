<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * Une portée déclarant `organization_id` doit correspondre exactement à
 * l'organisation de l'appartenance portant le grant : une liaison
 * individuelle sans appartenance ne peut jamais recevoir une portée
 * organisationnelle, et une appartenance ne peut jamais recevoir une portée
 * déclarant l'organisation d'une autre (P003-B1.1 §2).
 */
class SubjectOrganizationMismatchException extends RuntimeException {}
