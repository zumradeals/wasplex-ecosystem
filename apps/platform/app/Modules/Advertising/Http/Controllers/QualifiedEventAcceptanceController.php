<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\AcceptanceMode;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Huitième route sensible réelle de l'écosystème (P005-F) : validation d'un
 * `QualifiedEvent` `Pending` — consomme la réservation et répartit le net
 * distribuable 50/50 (ADR-0010 §4 ligne 4 ; AMD-0002). Première route
 * de tout le module à créer réellement un droit utilisateur payable.
 *
 * Réservée au personnel Wasplex anti-fraude/mesure d'attention, jamais au
 * bénéficiaire lui-même — voir le raisonnement documenté sur `event.accept`
 * (migration `2026_07_25_100010`).
 *
 * `CampaignBudgetService::acceptQualifiedEvent()` (P005-A, déjà écrit et
 * testé) reste l'unique décideur métier ; il rejoue sans second effet si
 * l'événement est déjà résolu (§4 de `01-cycle-creation-valeur.md`,
 * invariant 5), donc ce contrôleur n'a besoin d'aucune garde d'état
 * supplémentaire.
 */
class QualifiedEventAcceptanceController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly CampaignBudgetService $campaignBudgetService,
    ) {}

    public function store(Request $request, QualifiedEvent $qualifiedEvent): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'event.accept',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.qualified_event',
                resourceId: $qualifiedEvent->id,
                organizationId: null,
                ownerPersonId: $qualifiedEvent->beneficiary->person_id,
                countryCode: null,
                territoryCodes: [],
                environment: $this->currentEnvironment(),
            ),
            environment: $this->currentEnvironment(),
        );

        try {
            $this->authorizationGate->authorize($authorizationRequest);
        } catch (AuthorizationOutcomeException $exception) {
            return $this->failureResponder->forOutcome($exception);
        }

        // Décision humaine tracée comme telle (arbitrage Koné/SIRR
        // 2026-07-26) — depuis l'acceptation automatique des soumissions
        // propres, cette route est le chemin des cas suspects et des
        // dérogations, l'exception et non le défaut.
        $event = $this->campaignBudgetService->acceptQualifiedEvent($qualifiedEvent, AcceptanceMode::Manual);

        return response()->json([
            'qualified_event_id' => $event->id,
            'billing_status' => $event->billing_status->value,
            'acceptance_mode' => $event->acceptance_mode?->value,
        ], 200);
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
