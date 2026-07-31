<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Enums\AcceptanceMode;
use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Projections\PersonMonthlyQuotaProjection;
use App\Modules\Advertising\Services\Exceptions\CampaignNotAcceptingReservationsException;
use App\Modules\Advertising\Services\Exceptions\FrequencyCapExceededException;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Wallet\Balance\Services\PersonLedgerAccounts;
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
        private readonly PersonLedgerAccounts $personAccounts,
        private readonly CampaignBudgetProjection $budgetProjection,
        private readonly EconomicTypeResolver $economicTypeResolver,
        private readonly PersonMonthlyQuotaProjection $quotaProjection,
        private readonly FrequencyCapGuard $frequencyCapGuard,
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
        PersonAccountLink $beneficiary,
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

        // Plafond de revisionnage gratuit (instruction explicite du
        // fondateur, 2026-07-31) : une nouvelle soumission n'est acceptée
        // que si cette personne n'a pas déjà atteint le nombre maximal de
        // revisionnages, quotidien ou total, pour cette CampaignVersion
        // précise — barrière serveur en profondeur, le Feed cesse
        // normalement d'offrir cette publicité avant même d'y arriver
        // ({@see \App\Modules\Advertising\Http\Controllers\FeedController}).
        if ($this->frequencyCapGuard->hasReachedCap($beneficiary->id, $version->id)) {
            throw new FrequencyCapExceededException(
                'cette personne a déjà atteint le plafond de revisionnage gratuit pour cette CampaignVersion'
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
                'beneficiary_person_account_link_id' => $beneficiary->id,
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
     *
     * Depuis l'arbitrage Koné/SIRR 2026-07-26, l'acceptation porte sa
     * nature requêtable : `Manual` (décision humaine `event.accept`) ou
     * `Automatic` (contrôles serveur), auquel cas la version exacte des
     * règles appliquées est épinglée sur l'événement — exigence de schéma,
     * voir migration `2026_07_26_100001`.
     */
    public function acceptQualifiedEvent(
        QualifiedEvent $event,
        AcceptanceMode $acceptanceMode = AcceptanceMode::Manual,
        ?string $rulesConfigurationKey = null,
        ?int $rulesConfigurationVersion = null,
    ): QualifiedEvent {
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
        // impair, l'unité résiduelle revient à l'utilisateur, jamais à
        // Wasplex — décision du fondateur 2026-07-26 (ADR-0010, section
        // « Amendement 2026-07-26 — Arrondi du partage égal ») : quand
        // l'égalité exacte est impossible en unités entières, l'ambiguïté
        // se résout en faveur de la partie qu'AMD-0002 protège. Ferme
        // TD-0004-B. Ce montant reste le plafond de la part utilisateur
        // (docs/02 §5 : jamais dépasser la part utilisateur réellement
        // financée par la campagne) — le type économique ne peut que le
        // réduire, jamais l'augmenter.
        $standardUserShare = intdiv($amount + 1, 2);

        // Instruction explicite du fondateur 2026-07-31 (confirmée par
        // exemple concret Orange CI, 2026-07-31) : le type économique du
        // bénéficiaire ne réduit plus la part de CET événement — chaque
        // spectateur touche la part utilisateur standard pleine
        // (`$standardUserShare`) tant que la cagnotte de son type n'est pas
        // épuisée. Cette cagnotte est propre à la campagne, dimensionnée en
        // pourcentage de la part utilisateur totale déjà financée
        // (ex. gratuit 10 %, premium 25 %, gold 30 %, platinum 35 %,
        // totalisant 100 %) — voir {@see economicTypeSubPoolAllocated()}.
        // Cagnotte épuisée ou quota mensuel personnel dépassé (docs/02 §4,
        // décision confirmée : ne se consomme que sur un événement validé
        // qui paie réellement, évalué sur les événements déjà `accepted`
        // avant celui-ci, jamais sur lui-même) : deux raisons distinctes
        // d'un versement nul, chacune tracée dans sa propre colonne pour
        // ne jamais les confondre dans un audit. Le reliquat non versé
        // reste chez Wasplex : `$wasplexShare` se déduit toujours de
        // `$amount - $userShare`, jamais d'un second calcul indépendant,
        // pour que la conservation de valeur soit garantie par
        // construction (aucune fuite d'arrondi possible).
        // Récompense unique par personne et par CampaignVersion (instruction
        // explicite du fondateur, 2026-07-31) : un revisionnage au-delà de
        // ce premier événement accepté reste tracé et facturé normalement
        // à l'annonceur (exposition réelle), mais ne verse plus rien au
        // bénéficiaire — Wasplex absorbe l'intégralité du montant, comme
        // pour `quota_exceeded`/`economic_type_pool_exhausted` ci-dessous,
        // dans sa propre colonne pour ne jamais confondre les trois
        // raisons dans un audit. Le plafond de fréquence lui-même
        // (quotidien/total) est appliqué en amont, à la soumission
        // ({@see submitQualifiedEvent()}) — cette vérification-ci ne fait
        // que constater qu'une récompense a déjà eu lieu.
        $alreadyRewarded = QualifiedEvent::query()
            ->where('beneficiary_person_account_link_id', $event->beneficiary_person_account_link_id)
            ->where('campaign_version_id', $event->campaign_version_id)
            ->where('billing_status', BillingStatus::Accepted)
            ->exists();

        $economicType = $this->economicTypeResolver->forPerson($event->beneficiary->person_id);
        $quotaExceeded = $economicType->monthly_quota !== null
            && $this->quotaProjection->consumedThisMonth($event->beneficiary_person_account_link_id) >= $economicType->monthly_quota;

        $subPoolAllocated = $this->economicTypeSubPoolAllocated($campaign, $economicType);
        $subPoolConsumed = $this->economicTypeSubPoolConsumed($campaign, $economicType);
        $poolExhausted = $subPoolConsumed + $standardUserShare > $subPoolAllocated;

        $userShare = ($alreadyRewarded || $quotaExceeded || $poolExhausted) ? 0 : $standardUserShare;
        $wasplexShare = $amount - $userShare;

        // Dimensions conservées pour retrouver, par requête directe sur les
        // postings, l'événement qualifié précis à l'origine de ce crédit —
        // même sur le compte individuel du bénéficiaire, qui peut recevoir
        // plusieurs crédits au fil du temps (P006-A ferme TD-0004-F : le
        // compte `user_rights` n'est plus mutualisé par devise, il est
        // désormais provisionné par personne via `PersonLedgerAccounts`).
        $beneficiaryDimensions = [
            'qualified_event_id' => $event->id,
            'beneficiary_person_account_link_id' => $event->beneficiary_person_account_link_id,
        ];

        // Le Ledger refuse toute ligne de montant nul (invariant
        // structurel) : sur un montant de 1, la part Wasplex vaut 0
        // (unité résiduelle à l'utilisateur, décision du fondateur
        // 2026-07-26 ci-dessus) — la ligne correspondante est alors
        // simplement omise, la transaction restant équilibrée (débit =
        // somme des crédits).
        $distributionPostings = [
            new PostingLine($campaign->consumed_account_id, PostingDirection::Debit, $amount, $campaign->currency, "Répartition — {$event->format}"),
        ];

        if ($userShare > 0) {
            $beneficiaryAccount = $this->personAccounts->available($event->beneficiary->person_id, $campaign->currency);
            $distributionPostings[] = new PostingLine($beneficiaryAccount->id, PostingDirection::Credit, $userShare, $campaign->currency, "Part utilisateur — {$event->format}", $beneficiaryDimensions);
        }

        if ($wasplexShare > 0) {
            $distributionPostings[] = new PostingLine($this->sharedAccounts->wasplexRevenue($campaign->currency)->id, PostingDirection::Credit, $wasplexShare, $campaign->currency, "Part Wasplex — {$event->format}", $beneficiaryDimensions);
        }

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
            postings: $distributionPostings,
        ));

        $event->forceFill([
            'billing_status' => BillingStatus::Accepted,
            'acceptance_mode' => $acceptanceMode,
            'acceptance_rules_configuration_key' => $rulesConfigurationKey,
            'acceptance_rules_configuration_version' => $rulesConfigurationVersion,
            'consumption_transaction_id' => $consumption->id,
            'distribution_transaction_id' => $distribution->id,
            'user_share_amount' => $userShare,
            'economic_type_id' => $economicType->id,
            'economic_type_percentage_applied' => $economicType->user_share_percentage,
            'quota_exceeded' => $quotaExceeded,
            'economic_type_pool_exhausted' => $poolExhausted,
            'already_rewarded' => $alreadyRewarded,
        ])->save();

        return $event->fresh();
    }

    /**
     * Part utilisateur réellement créditée pour un événement déjà accepté
     * (instruction explicite du fondateur, 2026-07-31) : lit directement
     * `user_share_amount`, la valeur épinglée par
     * `acceptQualifiedEvent()` au moment de l'acceptation — jamais
     * recalculée après coup, puisqu'elle dépend du type économique et du
     * quota du bénéficiaire à cet instant précis, deux facteurs qui
     * peuvent changer depuis (docs/02 §6). Un événement non encore
     * `accepted` n'a pas de part utilisateur définitive : retombe sur le
     * plafond générique {@see userShareOfAmount()} (AMD-0002, 50 % exact,
     * sans aucune modulation par type), qui reste la seule information
     * disponible avant acceptation.
     */
    public function userShareOf(QualifiedEvent $event): int
    {
        return $event->user_share_amount ?? $this->userShareOfAmount($event->applied_price_amount);
    }

    /**
     * Même formule que {@see userShareOf()} mais applicable à un montant
     * brut avant qu'un `QualifiedEvent` n'existe (aperçu Feed, devis
     * annonceur) — extraite ici pour qu'il n'existe qu'un seul endroit où
     * le ratio 50/50 (AMD-0002) et son arrondi (ADR-0010, « Amendement
     * 2026-07-26 — Arrondi du partage égal ») sont exprimés.
     */
    public function userShareOfAmount(int $amount): int
    {
        return intdiv($amount + 1, 2);
    }

    /**
     * Part Wasplex symétrique de {@see userShareOfAmount()} — même
     * formule que la ligne `wasplexShare` de `acceptQualifiedEvent()`.
     */
    public function wasplexShareOfAmount(int $amount): int
    {
        return intdiv($amount, 2);
    }

    /**
     * Aperçu honnête du gain qu'une personne précise recevrait réellement
     * si elle validait cet événement maintenant (instruction explicite du
     * fondateur, 2026-07-31 ; docs/03 §10 : « le montant affiché avant la
     * participation doit être le montant effectivement crédité ») — utilisé
     * par le Feed, jamais un montant générique {@see userShareOfAmount()}
     * qui ignorerait le type économique, la cagnotte de campagne déjà
     * consommée pour ce type et le quota déjà consommé de cette personne
     * précise. Même formule exacte que {@see acceptQualifiedEvent()}, sans
     * écriture Ledger ni effet de bord : une lecture pure.
     */
    public function previewUserShareForPerson(int $amount, string $personId, string $personAccountLinkId, Campaign $campaign, CampaignVersion $version): int
    {
        $alreadyRewarded = QualifiedEvent::query()
            ->where('beneficiary_person_account_link_id', $personAccountLinkId)
            ->where('campaign_version_id', $version->id)
            ->where('billing_status', BillingStatus::Accepted)
            ->exists();

        if ($alreadyRewarded) {
            return 0;
        }

        $economicType = $this->economicTypeResolver->forPerson($personId);
        $quotaExceeded = $economicType->monthly_quota !== null
            && $this->quotaProjection->consumedThisMonth($personAccountLinkId) >= $economicType->monthly_quota;

        if ($quotaExceeded) {
            return 0;
        }

        $standardUserShare = intdiv($amount + 1, 2);
        $subPoolAllocated = $this->economicTypeSubPoolAllocated($campaign, $economicType);
        $subPoolConsumed = $this->economicTypeSubPoolConsumed($campaign, $economicType);

        if ($subPoolConsumed + $standardUserShare > $subPoolAllocated) {
            return 0;
        }

        return $standardUserShare;
    }

    /**
     * Taille de la cagnotte d'un type économique pour une campagne
     * précise (instruction explicite du fondateur, 2026-07-31) :
     * pourcentage du type appliqué à la part utilisateur totale déjà
     * financée sur cette campagne — jamais un pourcentage du montant brut
     * de la campagne, qui romprait le plafond 50/50 constitutionnel
     * (AMD-0002). Grandit automatiquement si l'annonceur recrédite la
     * campagne plus tard ({@see CampaignBudgetProjection::totalFunded()}).
     * Si la somme des pourcentages actifs ne totalise pas 100 %, la
     * différence n'est simplement jamais allouée à aucun type — aucune
     * fuite, aucun débordement inventé entre types (arbitrage explicite du
     * fondateur, 2026-07-31).
     */
    private function economicTypeSubPoolAllocated(Campaign $campaign, EconomicType $economicType): int
    {
        $totalUserPool = $this->userShareOfAmount($this->budgetProjection->totalFunded($campaign));

        return intdiv($totalUserPool * $economicType->user_share_percentage, 100);
    }

    /**
     * Montant déjà versé, sur cette campagne précise, aux bénéficiaires de
     * ce type économique précis — jamais un solde stocké, toujours
     * reconstruit depuis les `QualifiedEvent` déjà `accepted` (ADR-0003
     * §19, même discipline que le reste du Ledger).
     */
    private function economicTypeSubPoolConsumed(Campaign $campaign, EconomicType $economicType): int
    {
        return (int) QualifiedEvent::query()
            ->where('campaign_id', $campaign->id)
            ->where('economic_type_id', $economicType->id)
            ->where('billing_status', BillingStatus::Accepted)
            ->sum('user_share_amount');
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
