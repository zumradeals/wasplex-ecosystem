<?php

namespace App\Modules\Advertising\Services;

use App\Modules\Advertising\Models\PersonSubscription;
use App\Modules\Advertising\Models\SubscriptionPurchase;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use App\Modules\Wallet\Ledger\Services\TransactionIntent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Active un abonnement après paiement confirmé (instruction explicite du
 * fondateur, 2026-07-31) : crédite la part Wasplex directement (un
 * abonnement n'est jamais partagé 50/50 comme un événement publicitaire —
 * c'est un revenu direct de service, docs/02 §1) et prolonge ou crée
 * l'abonnement courant de la personne.
 *
 * Un nouvel achat pendant un abonnement encore actif prolonge à partir de
 * sa date de fin actuelle (jamais à partir d'aujourd'hui, qui perdrait la
 * période déjà payée) — un achat après expiration repart d'aujourd'hui.
 * Aucune proratisation, aucun remboursement de période non consommée en
 * cas de changement de plan (docs/02 §6 laisse ces règles ouvertes ;
 * défaut le plus simple, documenté comme tel, jamais une décision
 * commerciale silencieuse).
 */
class SubscriptionActivationService
{
    public function __construct(
        private readonly LedgerPoster $poster,
        private readonly SharedLedgerAccounts $sharedAccounts,
    ) {}

    public function activate(SubscriptionPurchase $purchase): LedgerTransaction
    {
        $plan = $purchase->subscriptionPlan;
        $coverage = $this->sharedAccounts->coverage($purchase->currency);
        $revenue = $this->sharedAccounts->wasplexRevenue($purchase->currency);

        return DB::transaction(function () use ($purchase, $plan, $coverage, $revenue): LedgerTransaction {
            $transaction = $this->poster->post(new TransactionIntent(
                type: 'subscription_purchase',
                businessDate: now(),
                accountingDate: now(),
                sourceModule: 'advertising',
                sourceReference: $purchase->id,
                idempotencyScope: 'advertising.subscription_purchase',
                idempotencyKey: $purchase->id,
                correlationId: $purchase->id,
                authoredBy: 'advertising.subscription_activation_service',
                postings: [
                    new PostingLine($coverage->id, PostingDirection::Debit, $purchase->amount, $purchase->currency, "Achat d'abonnement — actif de couverture"),
                    new PostingLine($revenue->id, PostingDirection::Credit, $purchase->amount, $purchase->currency, "Achat d'abonnement — {$plan->name}"),
                ],
            ));

            $existing = PersonSubscription::query()->where('person_id', $purchase->person_id)->first();
            $startsAt = $existing !== null && $existing->ends_at->isFuture() ? $existing->ends_at : Carbon::now();
            $endsAt = $startsAt->copy()->addDays($plan->duration_days);

            if ($existing !== null) {
                $existing->forceFill([
                    'subscription_plan_id' => $plan->id,
                    'ends_at' => $endsAt,
                ])->save();
            } else {
                PersonSubscription::create([
                    'person_id' => $purchase->person_id,
                    'subscription_plan_id' => $plan->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);
            }

            return $transaction;
        });
    }
}
