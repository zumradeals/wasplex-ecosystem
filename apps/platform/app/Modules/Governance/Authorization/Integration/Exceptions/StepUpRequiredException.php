<?php

namespace App\Modules\Governance\Authorization\Integration\Exceptions;

use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Integration\AuthenticatedSubject;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;

/**
 * Décision `step_up_required` : une session plus forte pourrait suffire,
 * mais aucune élévation n'est jamais déclenchée automatiquement ici — le
 * module appelant reste seul responsable d'inviter l'utilisateur à
 * renforcer sa session (P003-B2 §C, TD-0001-B).
 *
 * {@see AuthorizationFailureResponder::forOutcome()}
 * traduit cette exception en réponse HTTP indiquant le palier requis et le
 * type d'action attendue, sans jamais construire elle-même un parcours de
 * renforcement (redirection, retour, rejeu automatique) : cela reste hors
 * périmètre, à la charge du module appelant au moment d'une route
 * sensible réelle (TD-0002-B).
 *
 * Cette exception, comme le {@see AuthorizationResult}
 * qu'elle porte, ne constitue jamais une preuve réutilisable : elle ne peut
 * pas être injectée dans {@see AuthorizationGate}
 * pour obtenir une autorisation — seule une {@see AuthorizationRequest}
 * fraîche, reconstruite depuis un {@see AuthenticatedSubject}
 * entièrement réévalué après le renforcement réel de la session, peut
 * aboutir à une nouvelle décision (TD-0002-B).
 */
class StepUpRequiredException extends AuthorizationOutcomeException {}
