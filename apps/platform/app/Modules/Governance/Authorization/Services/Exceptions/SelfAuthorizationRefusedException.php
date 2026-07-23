<?php

namespace App\Modules\Governance\Authorization\Services\Exceptions;

use RuntimeException;

/**
 * L'auteur d'un grant ne peut créer et activer seul sa propre habilitation
 * (ADR-0004 §9, §12 ; ecosystem/gouvernance/01 §4).
 *
 * Cette règle est appliquée sans exception, y compris pour une capacité
 * `ordinary` : aucune capacité, quelle que soit sa classe de risque, ne
 * permet à une personne de s'auto-habiliter seule. Cette lecture stricte a
 * été confirmée explicitement (revue P003-B1.1 §5). Elle ne peut être
 * assouplie que par une nouvelle décision normative explicite (ADR ou
 * amendement) — jamais implicitement par une modification de ce code.
 */
class SelfAuthorizationRefusedException extends RuntimeException {}
