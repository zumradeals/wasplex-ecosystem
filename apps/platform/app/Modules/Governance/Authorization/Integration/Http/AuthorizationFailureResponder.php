<?php

namespace App\Modules\Governance\Authorization\Integration\Http;

use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use Illuminate\Http\JsonResponse;

/**
 * Traduit un refus d'autorisation en réponse HTTP sûre et structurée
 * (P003-B2 §D).
 *
 * N'expose jamais le grant retenu, la politique appliquée, ni aucun détail
 * susceptible de faciliter une attaque (ADR-0004 §"décision explicable").
 * Seuls la famille de décision, un code de motif non sensible et
 * l'identifiant de corrélation — déjà dénué de donnée sensible — sont
 * restitués.
 */
final class AuthorizationFailureResponder
{
    public function forOutcome(AuthorizationOutcomeException $exception): JsonResponse
    {
        return new JsonResponse([
            'decision' => $exception->result->decision->value,
            'reason' => $exception->result->reason->code,
            'correlation_id' => $exception->result->correlationId,
        ], 403);
    }

    public function forUnresolvedSubject(SubjectResolutionFailedException $exception): JsonResponse
    {
        return new JsonResponse([
            'decision' => 'denied',
            'reason' => 'subject_not_resolved',
        ], 401);
    }
}
