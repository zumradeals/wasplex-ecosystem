<?php

namespace App\Modules\Governance\Authorization\Integration\Http;

use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\StepUpRequiredException;
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
 *
 * Pour `step_up_required` uniquement (TD-0002-B), deux champs non sensibles
 * s'ajoutent : le palier de session requis (`required_session_assurance`,
 * déjà porté par la décision elle-même — jamais une nouvelle donnée
 * inventée ici) et un indice non sensible du type d'action Fortify attendue
 * (`step_up_action`), pour qu'un client sache vers quelle action se
 * diriger sans jamais recevoir le grant ni la politique. Cette réponse ne
 * construit aucun parcours de renforcement : ni redirection, ni retour, ni
 * rejeu automatique de la requête d'origine — cela reste la responsabilité
 * du module appelant au moment d'une véritable route sensible (TD-0002-B,
 * porte de reprise).
 */
final class AuthorizationFailureResponder
{
    /**
     * Fortify n'expose aucun signal fiable distinguant aujourd'hui un login
     * `standard` (TD-0002-A) : ce mappage reste documenté pour la forme
     * théorique attendue par un futur client, mais aucune capacité réelle
     * n'exige encore `standard` (mesure temporaire TD-0002-A inchangée).
     *
     * @var array<string, string>
     */
    private const STEP_UP_ACTIONS = [
        'standard' => 'two_factor_or_passkey_verification',
        'strong' => 'password_confirmation',
    ];

    public function forOutcome(AuthorizationOutcomeException $exception): JsonResponse
    {
        $payload = [
            'decision' => $exception->result->decision->value,
            'reason' => $exception->result->reason->code,
            'correlation_id' => $exception->result->correlationId,
        ];

        if ($exception instanceof StepUpRequiredException) {
            $requiredSessionAssurance = $this->requiredSessionAssurance($exception);

            if ($requiredSessionAssurance !== null) {
                $payload['required_session_assurance'] = $requiredSessionAssurance;
                $payload['step_up_action'] = self::STEP_UP_ACTIONS[$requiredSessionAssurance] ?? null;
            }
        }

        return new JsonResponse($payload, 403);
    }

    public function forUnresolvedSubject(SubjectResolutionFailedException $exception): JsonResponse
    {
        return new JsonResponse([
            'decision' => 'denied',
            'reason' => 'subject_not_resolved',
        ], 401);
    }

    private function requiredSessionAssurance(StepUpRequiredException $exception): ?string
    {
        foreach ($exception->result->obligations as $obligation) {
            if ($obligation->type === 'required_session_assurance') {
                return $obligation->payload['minimum_session_assurance'] ?? null;
            }
        }

        return null;
    }
}
