<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * `governance.system_administrator` n'admet qu'un seul grant actif à la
 * fois dans tout le système (amendement ADR-0004 2026-07-30) — révoquer le
 * titulaire actuel avant d'en activer un nouveau.
 */
class MultipleSystemAdministratorsRefusedException extends RuntimeException {}
