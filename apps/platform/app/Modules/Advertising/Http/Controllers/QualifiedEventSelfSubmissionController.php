<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Enums\AcceptanceMode;
use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\CampaignVersionState;
use App\Modules\Advertising\Http\Requests\StoreSelfQualifiedEventRequest;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\Exceptions\CampaignNotAcceptingReservationsException;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Advertising\Services\Exceptions\PricingConfigurationNotResolvableException;
use App\Modules\Advertising\Services\QualifiedEventAutoAcceptancePolicy;
use App\Modules\Advertising\Services\QualifiedEventPricingResolver;
use App\Modules\Governance\Authorization\Contracts\ResourceContext;
use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Governance\Authorization\Integration\AuthorizationGate;
use App\Modules\Governance\Authorization\Integration\AuthorizationRequestFactory;
use App\Modules\Governance\Authorization\Integration\Exceptions\AuthorizationOutcomeException;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use App\Modules\Governance\Authorization\Integration\Http\AuthorizationFailureResponder;
use App\Modules\Wallet\Balance\Projections\PersonBalanceProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Auto-soumission par le bénéficiaire de sa propre preuve d'attention
 * qualifiée (W2, `event.self_submit`) — ferme la dette documentée sur
 * `event.submit` (migration `2026_07_25_100009`, TD-0004) : le prix est
 * désormais résolu par {@see QualifiedEventPricingResolver} via le registre
 * Configuration, jamais transmis par l'appelant, ce qui rend enfin ce flux
 * sûr en libre-service.
 *
 * Distincte et sans effet sur
 * {@see QualifiedEventSubmissionController} (personnel Wasplex, portée
 * large, montant pré-vérifié hors système) : les deux routes coexistent,
 * la seconde restant un outil de dérogation anti-fraude.
 *
 * Depuis l'arbitrage Koné/SIRR 2026-07-26, une soumission propre est
 * acceptée automatiquement dans la même requête si les contrôles
 * déterministes de {@see QualifiedEventAutoAcceptancePolicy} passent tous
 * (règles versionnées actives, complétion attestée, quota journalier) :
 * la réponse porte alors le montant réellement crédité et le nouveau
 * solde — le gain annoncé au client EST comptabilisé (UX-0001 §10), sans
 * validation humaine par vue. L'examen humain (`event.accept`) reste le
 * chemin des cas suspects, l'exception et non le défaut.
 */
class QualifiedEventSelfSubmissionController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
        private readonly AuthorizationRequestFactory $authorizationRequestFactory,
        private readonly AuthorizationGate $authorizationGate,
        private readonly AuthorizationFailureResponder $failureResponder,
        private readonly QualifiedEventPricingResolver $pricingResolver,
        private readonly QualifiedEventAutoAcceptancePolicy $autoAcceptancePolicy,
        private readonly CampaignBudgetService $campaignBudgetService,
        private readonly PersonBalanceProjection $balanceProjection,
    ) {}

    public function store(StoreSelfQualifiedEventRequest $request, CampaignVersion $campaignVersion): JsonResponse
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException $exception) {
            return $this->failureResponder->forUnresolvedSubject($exception);
        }

        $campaign = $campaignVersion->campaign;

        $authorizationRequest = $this->authorizationRequestFactory->make(
            subject: $subject,
            capabilityKey: 'event.self_submit',
            operation: Operation::Write,
            resource: new ResourceContext(
                resourceType: 'advertising.campaign',
                resourceId: $campaign->id,
                organizationId: null,
                // Portée self : le bénéficiaire réel de cet événement est
                // toujours le sujet authentifié lui-même — jamais le
                // représentant de l'annonceur (à la différence de
                // QualifiedEventSubmissionController, dont la portée large
                // ne s'appuie jamais sur ownerPersonId).
                ownerPersonId: $subject->personAccountLink->person_id,
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

        if ($campaignVersion->state !== CampaignVersionState::Approved) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'campaign_version_not_approved',
            ], 409);
        }

        try {
            $appliedPriceAmount = $this->pricingResolver->resolveBasePrice($campaignVersion);
        } catch (PricingConfigurationNotResolvableException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'pricing_not_resolvable',
            ], 409);
        }

        // Rejeu idempotent (même preuve, même clé) : renvoyer l'état déjà
        // décidé sans réévaluer les règles — jamais deux évaluations pour
        // une même preuve (ADR-0010 §3, §7).
        $existing = QualifiedEvent::query()
            ->where('idempotency_key', $request->validated('idempotency_key'))
            ->first();
        if ($existing !== null) {
            return $this->eventResponse($existing);
        }

        $assessment = $this->autoAcceptancePolicy->assess(
            $subject->personAccountLink,
            $request->validated('evidence'),
        );

        try {
            $event = $this->campaignBudgetService->submitQualifiedEvent(
                campaign: $campaign,
                version: $campaignVersion,
                beneficiary: $subject->personAccountLink,
                format: $request->validated('format'),
                evidence: $request->validated('evidence'),
                appliedPriceAmount: $appliedPriceAmount,
                idempotencyKey: $request->validated('idempotency_key'),
                correlationId: (string) Str::uuid(),
                fraudDecision: $assessment->fraudDecision,
                pricingConfigurationKey: $campaignVersion->pricing_configuration_key,
                pricingConfigurationVersion: $campaignVersion->pricing_configuration_version,
            );
        } catch (CampaignNotAcceptingReservationsException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'campaign_not_accepting_reservations',
            ], 409);
        } catch (InsufficientBudgetException $exception) {
            return new JsonResponse([
                'decision' => 'denied',
                'reason' => 'insufficient_budget',
            ], 409);
        }

        if ($assessment->accepts && $event->billing_status === BillingStatus::Pending) {
            $event = $this->campaignBudgetService->acceptQualifiedEvent(
                $event,
                AcceptanceMode::Automatic,
                $assessment->rulesConfigurationKey,
                $assessment->rulesConfigurationVersion,
            );
        }

        return $this->eventResponse($event, $assessment->holdReason);
    }

    /**
     * Réponse honnête sur l'état réellement comptabilisé (UX-0001 §10) :
     * un événement accepté annonce le montant crédité et le nouveau solde
     * disponible ; un événement maintenu en attente annonce sa raison
     * d'examen, jamais un gain.
     */
    private function eventResponse(QualifiedEvent $event, ?string $reviewReason = null): JsonResponse
    {
        $payload = [
            'qualified_event_id' => $event->id,
            'billing_status' => $event->billing_status->value,
            'acceptance_mode' => $event->acceptance_mode?->value,
        ];

        if ($event->billing_status === BillingStatus::Accepted) {
            $payload['credited_amount'] = $this->campaignBudgetService->userShareOf($event);
            $payload['credited_currency'] = $event->applied_price_currency;
            $payload['wallet_available'] = $this->walletAvailableFor($event);
        } else {
            $payload['review_reason'] = $reviewReason;
        }

        return response()->json($payload, 201);
    }

    private function walletAvailableFor(QualifiedEvent $event): int
    {
        foreach ($this->balanceProjection->forPerson($event->beneficiary->person_id) as $balance) {
            if ($balance['currency'] === $event->applied_price_currency) {
                return $balance['available'];
            }
        }

        return 0;
    }

    private function currentEnvironment(): Environment
    {
        return Environment::tryFrom(app()->environment()) ?? Environment::Production;
    }
}
