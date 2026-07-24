<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\Exceptions\CampaignNotAcceptingReservationsException;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;
use Illuminate\Database\QueryException;

/**
 * Câblage exact du cycle financier d'une campagne sur `LedgerPoster`
 * (ADR-0010 §4). Chaque transition passe exclusivement par
 * `LedgerPoster::post()` ou `reverse()` — jamais d'écriture directe dans
 * `ledger.*` (ADR-0010 §2, §7). Le solde par état est toujours lu via
 * {@see CampaignBudgetProjection}, jamais stocké ici.
 *
 * Aucune formule de prix n'est calculée par cette classe (ADR-0010 §4,
 * §8) : `applied_price_amount` est fourni par l'appelant (future
 * configuration versionnée), jamais recalculé en dur.
 */
class CampaignBudgetService
{
    public function __construct(
        private readonly LedgerPoster $poster,
        private readonly SharedLedgerAccounts $sharedAccounts,
        private readonly CampaignBudgetProjection $budgetProjection,
    ) {}

    /**
     * Financement reçu (ADR-0010 §4, ligne 1) : débit de l'actif de
     * couverture partagé, crédit du passif « budget campagne — disponible ».
     */
    public function fund(Campaign $campaign, int $amount, string $fundingReference, string $correlationId): LedgerTransaction
    {
        $coverage = $this->sharedAccounts->coverage($campaign->currency);

        return $this->poster->post(new TransactionIntent(
            type: 'advertising_campaign_funding',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'advertising',
            sourceReference: $fundingReference,
            idempotencyScope: 'advertising.funding',
            idempotencyKey: $fundingReference,
            correlationId: $correlationId,
            authoredBy: 'advertising.campaign_budget_service',
            postings: [
                new PostingLine($coverage->id, PostingDirection::Debit, $amount, $campaign->currency, 'Financement reçu — actif de couverture'),
                new PostingLine($campaign->available_account_id, PostingDirection::Credit, $amount, $campaign->currency, "Financement reçu — budget disponible ({$campaign->code})"),
            ],
        ));
    }

    /**
     * Avant exécution (ADR-0010 §4, ligne 2) : vérification atomique de
     * solde disponible, aucune écriture.
     *
     * @throws InsufficientBudgetException
     */
    public function assertSufficientAvailable(Campaign $campaign, int $amount): void
    {
        $available = $this->budgetProjection->available($campaign);

        if ($amount > $available) {
            throw new InsufficientBudgetException(
                "budget disponible insuffisant pour la campagne {$campaign->code} : {$amount} demandé, {$available} disponible (02-cycle-financier-campagne.md §4.1)"
            );
        }
    }

    /**
     * Pendant contrôle (ADR-0010 §4, ligne 3) : réserve le coût maximal
     * applicable et crée le QualifiedEvent correspondant. Une campagne
     * suspendue ne peut plus engager de nouvelle réservation (ADR-0010
     * §7) ; les réservations déjà engagées ne passent pas par cette
     * méthode et ne sont donc pas concernées par ce refus.
     *
     * @param  array<string, mixed>  $evidence
     *
     * @throws CampaignNotAcceptingReservationsException
     * @throws InsufficientBudgetException
     */
    public function submitQualifiedEvent(
        Campaign $campaign,
        CampaignVersion $version,
        string $format,
        array $evidence,
        int $appliedPriceAmount,
        string $idempotencyKey,
        string $correlationId,
        FraudDecision $fraudDecision = FraudDecision::None,
        ?string $pricingConfigurationKey = null,
        ?int $pricingConfigurationVersion = null,
    ): QualifiedEvent {
        // Une même preuve (même clé d'idempotence) ne produit jamais deux
        // facturations ni deux rémunérations (ADR-0010 §3, §7) : rejoue
        // l'événement déjà connu plutôt que de retenter la réservation.
        $existing = QualifiedEvent::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        if ($campaign->state !== CampaignState::Active) {
            throw new CampaignNotAcceptingReservationsException(
                "la campagne {$campaign->code} n'accepte plus de nouvelle réservation dans son état actuel ({$campaign->state->value}), ADR-0010 §7"
            );
        }

        $this->assertSufficientAvailable($campaign, $appliedPriceAmount);

        $reservation = $this->poster->post(new TransactionIntent(
            type: 'advertising_campaign_reservation',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'advertising',
            sourceReference: $idempotencyKey,
            idempotencyScope: 'advertising.reservation',
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId,
            authoredBy: 'advertising.campaign_budget_service',
            postings: [
                new PostingLine($campaign->available_account_id, PostingDirection::Debit, $appliedPriceAmount, $campaign->currency, "Réservation — {$format}"),
                new PostingLine($campaign->reserved_account_id, PostingDirection::Credit, $appliedPriceAmount, $campaign->currency, "Réservation — {$format}"),
            ],
        ));

        try {
            return QualifiedEvent::create([
                'campaign_id' => $campaign->id,
                'campaign_version_id' => $version->id,
                'format' => $format,
                'evidence' => $evidence,
                'occurred_at' => now(),
                'fraud_decision' => $fraudDecision,
                'applied_price_amount' => $appliedPriceAmount,
                'applied_price_currency' => $campaign->currency,
                'pricing_configuration_key' => $pricingConfigurationKey,
                'pricing_configuration_version' => $pricingConfigurationVersion,
                'billing_status' => BillingStatus::Pending,
                'reservation_transaction_id' => $reservation->id,
                'correlation_id' => $correlationId,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (QueryException $exception) {
            // Course perdue entre notre lecture et notre écriture (la
            // réservation Ledger, elle, vient de le prouver ci-dessus en
            // renvoyant une transaction déjà comptabilisée par une session
            // concurrente) : même garantie de secours que LedgerPoster.
            $raceWinner = QualifiedEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($raceWinner !== null) {
                return $raceWinner;
            }

            throw $exception;
        }
    }

    /**
     * Validation (ADR-0010 §4, ligne 4) : réservé → consommé, puis
     * répartition du net distribuable au ratio fixe 50/50 (AMD-0002, non
     * paramétrable). Ce noyau ne modélise ni taxe ni frais externe
     * (ADR-0010 §8, hors périmètre) : le net distribuable égale ici le
     * prix appliqué. Rejoue sans second effet si l'événement est déjà
     * résolu (même preuve, même clé — ADR-0010 §7).
     */
    public function acceptQualifiedEvent(QualifiedEvent $event): QualifiedEvent
    {
        if ($event->billing_status !== BillingStatus::Pending) {
            return $event->fresh();
        }

        $campaign = $event->campaign;
        $amount = $event->applied_price_amount;

        $consumption = $this->poster->post(new TransactionIntent(
            type: 'advertising_campaign_consumption',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'advertising',
            sourceReference: $event->idempotency_key,
            idempotencyScope: 'advertising.consumption',
            idempotencyKey: $event->idempotency_key.'-consumption',
            correlationId: $event->correlation_id,
            authoredBy: 'advertising.campaign_budget_service',
            postings: [
                new PostingLine($campaign->reserved_account_id, PostingDirection::Debit, $amount, $campaign->currency, "Consommation — {$event->format}"),
                new PostingLine($campaign->consumed_account_id, PostingDirection::Credit, $amount, $campaign->currency, "Consommation — {$event->format}"),
            ],
        ));

        // Ratio 50/50 constitutionnel exact (AMD-0002) : sur un montant
        // impair, l'unité résiduelle est absorbée par la part Wasplex —
        // règle d'arrondi explicite (ADR-0002 §5), en l'absence d'un
        // registre de configuration central pour la porter formellement
        // (TD-0004).
        $userShare = intdiv($amount, 2);
        $wasplexShare = $amount - $userShare;

        $distribution = $this->poster->post(new TransactionIntent(
            type: 'advertising_campaign_distribution',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'advertising',
            sourceReference: $event->idempotency_key,
            idempotencyScope: 'advertising.distribution',
            idempotencyKey: $event->idempotency_key.'-distribution',
            correlationId: $event->correlation_id,
            authoredBy: 'advertising.campaign_budget_service',
            postings: [
                new PostingLine($campaign->consumed_account_id, PostingDirection::Debit, $amount, $campaign->currency, "Répartition — {$event->format}"),
                new PostingLine($this->sharedAccounts->userRights($campaign->currency)->id, PostingDirection::Credit, $userShare, $campaign->currency, "Part utilisateur — {$event->format}"),
                new PostingLine($this->sharedAccounts->wasplexRevenue($campaign->currency)->id, PostingDirection::Credit, $wasplexShare, $campaign->currency, "Part Wasplex — {$event->format}"),
            ],
        ));

        $event->forceFill([
            'billing_status' => BillingStatus::Accepted,
            'consumption_transaction_id' => $consumption->id,
            'distribution_transaction_id' => $distribution->id,
        ])->save();

        return $event->fresh();
    }

    /**
     * Rejet ou expiration (ADR-0010 §4, ligne 5) : contre-écriture
     * explicite de la réservation d'origine, jamais une nouvelle
     * transaction libre (ADR-0003 §11). Rejoue sans second effet si
     * l'événement est déjà résolu.
     */
    public function rejectQualifiedEvent(QualifiedEvent $event, string $reason): QualifiedEvent
    {
        if ($event->billing_status !== BillingStatus::Pending) {
            return $event->fresh();
        }

        $campaign = $event->campaign;
        $amount = $event->applied_price_amount;

        $release = $this->poster->reverse(
            $event->reservationTransaction,
            new TransactionIntent(
                type: 'advertising_campaign_release',
                businessDate: now(),
                accountingDate: now(),
                sourceModule: 'advertising',
                sourceReference: $event->idempotency_key,
                idempotencyScope: 'advertising.release',
                idempotencyKey: $event->idempotency_key.'-release',
                correlationId: $event->correlation_id,
                authoredBy: 'advertising.campaign_budget_service',
                postings: [
                    new PostingLine($campaign->reserved_account_id, PostingDirection::Debit, $amount, $campaign->currency, "Libération — {$event->format}"),
                    new PostingLine($campaign->available_account_id, PostingDirection::Credit, $amount, $campaign->currency, "Libération — {$event->format}"),
                ],
            ),
            $reason,
        );

        $event->forceFill([
            'billing_status' => BillingStatus::Rejected,
            'release_transaction_id' => $release->id,
        ])->save();

        return $event->fresh();
    }
}
