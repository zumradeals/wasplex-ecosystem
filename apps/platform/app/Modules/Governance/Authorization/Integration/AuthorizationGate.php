<?php

namespace App\Modules\Governance\Authorization\Integration;

use App\Modules\Governance\Authorization\Contracts\AuthorizationRequest;
use App\Modules\Governance\Authorization\Contracts\AuthorizationResult;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Integration\Exceptions\ApprovalRequiredException;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationDeniedException;
use App\Modules\Governance\Authorization\Integration\Exceptions\StepUpRequiredException;
use App\Modules\Governance\Authorization\Services\AuthorizationEngine;

/**
 * Point d'application commun du moteur d'autorisation (P003-B2 §C).
 *
 * Contrôleurs, commandes et workers passent tous par ce même service, qui
 * délègue chaque décision à l'unique {@see AuthorizationEngine}. Aucun
 * refus n'est jamais transformé silencieusement en autorisation, et aucune
 * approbation ou élévation de session n'est jamais exécutée automatiquement
 * ici : le module appelant conserve toujours sa décision métier finale.
 */
final class AuthorizationGate
{
    public function __construct(
        private readonly AuthorizationEngine $engine,
    ) {}

    /**
     * Évalue la requête et ne renvoie jamais rien d'autre qu'une décision
     * `allowed`, `allowed_masked` ou `allowed_read_only`. Toute autre issue
     * lève une exception typée : au module appelant de la traiter, jamais
     * de la contourner.
     *
     * @throws AuthorizationDeniedException
     * @throws StepUpRequiredException
     * @throws ApprovalRequiredException
     */
    public function authorize(AuthorizationRequest $request): AuthorizationResult
    {
        $result = $this->engine->evaluate($request);

        return match ($result->decision) {
            AuthorizationDecision::Allowed,
            AuthorizationDecision::AllowedMasked,
            AuthorizationDecision::AllowedReadOnly => $result,
            AuthorizationDecision::Denied => throw new AuthorizationDeniedException($result),
            AuthorizationDecision::StepUpRequired => throw new StepUpRequiredException($result),
            AuthorizationDecision::ApprovalRequired => throw new ApprovalRequiredException($result),
        };
    }

    /**
     * Évalue la requête sans jamais lever d'exception : au module appelant
     * de brancher explicitement sur chaque famille de décision. Utile
     * lorsque le contrôle de flux par exception ne convient pas, ou pour
     * inspecter une décision avant de choisir comment réagir.
     */
    public function evaluate(AuthorizationRequest $request): AuthorizationResult
    {
        return $this->engine->evaluate($request);
    }
}
