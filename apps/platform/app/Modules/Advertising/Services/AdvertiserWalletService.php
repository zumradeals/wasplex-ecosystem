<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\AdvertiserProfile;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Projections\AdvertiserWalletBalanceProjection;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;

/**
 * Solde annonceur mutualisé (instruction explicite du fondateur, 2026-07-31)
 * : un dépôt libre-service crédite ce solde commun, jamais une campagne
 * directement — une allocation ultérieure et distincte (`allocateToCampaign`)
 * déplace ensuite tout ou partie de ce solde vers une campagne précise.
 * Même câblage exact que {@see CampaignBudgetService} sur `LedgerPoster` :
 * chaque mouvement passe exclusivement par `post()`, jamais d'écriture
 * directe dans `ledger.*`, le solde par compte est toujours lu via
 * {@see AdvertiserWalletBalanceProjection} ou
 * {@see CampaignBudgetProjection},
 * jamais stocké ici.
 */
class AdvertiserWalletService
{
    public function __construct(
        private readonly LedgerPoster $poster,
        private readonly SharedLedgerAccounts $sharedAccounts,
        private readonly AdvertiserWalletLedgerAccounts $walletAccounts,
        private readonly AdvertiserWalletBalanceProjection $walletBalance,
    ) {}

    /**
     * Dépôt confirmé (webhook signé uniquement, jamais une déclaration de
     * l'annonceur) : débit de l'actif de couverture partagé, crédit du
     * solde annonceur disponible — même paire de comptes que
     * {@see CampaignBudgetService::fund()}, seul le compte crédité change.
     */
    public function deposit(AdvertiserProfile $advertiser, string $currency, int $amount, string $fundingReference, string $correlationId): LedgerTransaction
    {
        $coverage = $this->sharedAccounts->coverage($currency);
        $available = $this->walletAccounts->available($advertiser, $currency);

        return $this->poster->post(new TransactionIntent(
            type: 'advertiser_wallet_deposit',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'advertising',
            sourceReference: $fundingReference,
            idempotencyScope: 'advertising.wallet_deposit',
            idempotencyKey: $fundingReference,
            correlationId: $correlationId,
            authoredBy: 'advertising.advertiser_wallet_service',
            postings: [
                new PostingLine($coverage->id, PostingDirection::Debit, $amount, $currency, 'Dépôt Wallet annonceur — actif de couverture'),
                new PostingLine($available->id, PostingDirection::Credit, $amount, $currency, "Dépôt Wallet annonceur — solde disponible ({$advertiser->legal_name})"),
            ],
        ));
    }

    /**
     * Allocation d'un montant du solde annonceur vers le budget disponible
     * d'une campagne précise (instruction explicite du fondateur, 2026-07-31)
     * : un simple transfert interne — aucune couverture supplémentaire
     * n'entre dans le système, la valeur était déjà couverte au dépôt
     * ci-dessus. Le compte Wallet consulté est toujours celui de la devise
     * de la campagne elle-même (aucun taux de change n'existe dans ce
     * système : un solde Wallet dans une autre devise n'est simplement pas
     * consulté ici). Refuse un montant supérieur au solde disponible dans
     * cette devise (même garde que
     * {@see CampaignBudgetService::assertSufficientAvailable()}).
     *
     * @throws InsufficientBudgetException
     */
    public function allocateToCampaign(AdvertiserProfile $advertiser, Campaign $campaign, int $amount, string $idempotencyKey, string $correlationId): LedgerTransaction
    {
        $walletAvailable = $this->walletAccounts->available($advertiser, $campaign->currency);

        $balance = $this->walletBalance->forAdvertiser($advertiser);
        $currencyBalance = collect($balance)->firstWhere('currency', $campaign->currency);
        $available = $currencyBalance['available'] ?? 0;

        if ($amount > $available) {
            throw new InsufficientBudgetException(
                "solde Wallet annonceur insuffisant pour {$advertiser->legal_name} en {$campaign->currency} : {$amount} demandé, {$available} disponible"
            );
        }

        return $this->poster->post(new TransactionIntent(
            type: 'advertiser_wallet_allocation',
            businessDate: now(),
            accountingDate: now(),
            sourceModule: 'advertising',
            sourceReference: $idempotencyKey,
            idempotencyScope: 'advertising.wallet_allocation',
            idempotencyKey: $idempotencyKey,
            correlationId: $correlationId,
            authoredBy: 'advertising.advertiser_wallet_service',
            postings: [
                new PostingLine($walletAvailable->id, PostingDirection::Debit, $amount, $campaign->currency, "Allocation vers campagne — {$campaign->code}"),
                new PostingLine($campaign->available_account_id, PostingDirection::Credit, $amount, $campaign->currency, "Allocation depuis le Wallet annonceur — {$campaign->code}"),
            ],
        ));
    }
}
