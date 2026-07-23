<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * L'auteur transmis à l'activation d'un grant doit être exactement celui
 * enregistré à sa proposition : aucune substitution d'auteur n'est jamais
 * possible entre `propose()` et `activate()` (TD-0001-A).
 */
class AuthorSubstitutionRefusedException extends RuntimeException {}
