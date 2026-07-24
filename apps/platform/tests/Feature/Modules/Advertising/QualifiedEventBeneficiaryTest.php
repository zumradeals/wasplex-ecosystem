<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Services\SharedLedgerAccounts;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\Posting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * `01-cycle-creation-valeur.md` §4, §10 : un événement qualifié rémunère
 * toujours une personne précise, jamais un agrégat anonyme — correction
 * d'une sous-spécification d'ADR-0010 §3 (voir TD-0004-F).
 * `beneficiary_person_account_link_id` référence le même sujet que
 * Governance/Authorization (P003-B2, `AuthenticatedSubject::$personAccountLink`).
 */
class QualifiedEventBeneficiaryTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_qualified_event_without_a_beneficiary_is_refused_at_creation(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $beneficiary = $this->makeBeneficiary();

        // Réutilise une réservation Ledger réellement comptabilisée pour
        // isoler l'échec sur la seule colonne bénéficiaire, plutôt que sur
        // une contrainte étrangère non liée à ce test.
        $reference = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, beneficiary: $beneficiary, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );

        $this->expectException(QueryException::class);

        QualifiedEvent::create([
            'campaign_id' => $campaign->id,
            'campaign_version_id' => $version->id,
            // 'beneficiary_person_account_link_id' délibérément omis.
            'format' => 'banner',
            'evidence' => ['proof' => 'completion'],
            'occurred_at' => now(),
            'fraud_decision' => FraudDecision::None,
            'applied_price_amount' => 1_000,
            'applied_price_currency' => $campaign->currency,
            'billing_status' => BillingStatus::Pending,
            'reservation_transaction_id' => $reference->reservation_transaction_id,
            'correlation_id' => (string) Str::uuid(),
            'idempotency_key' => 'no-beneficiary-'.Str::uuid(),
        ]);
    }

    public function test_the_user_rights_credit_posting_carries_the_beneficiary_dimension_and_is_queryable_by_person(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $beneficiary = $this->makeBeneficiary();

        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign, version: $version, beneficiary: $beneficiary, format: 'banner',
            evidence: ['proof' => 'completion'], appliedPriceAmount: 2_000,
            idempotencyKey: (string) Str::uuid(), correlationId: (string) Str::uuid(),
        );

        $this->assertSame($beneficiary->id, $event->beneficiary_person_account_link_id);

        $accepted = $this->budgetService()->acceptQualifiedEvent($event);

        $userRightsAccountId = app(SharedLedgerAccounts::class)->userRights($campaign->currency)->id;

        // Retrouvable par requête sur les postings eux-mêmes (pas seulement
        // via la transaction), grâce à la dimension portée par le posting de
        // crédit `user_rights` — le compte reste mutualisé par devise, mais
        // les droits dus à cette personne restent reconstructibles.
        $creditToBeneficiary = Posting::query()
            ->where('account_id', $userRightsAccountId)
            ->where('direction', PostingDirection::Credit)
            ->where('dimensions->beneficiary_person_account_link_id', $beneficiary->id)
            ->get();

        $this->assertCount(1, $creditToBeneficiary);
        $posting = $creditToBeneficiary->first();
        $this->assertSame($accepted->distribution_transaction_id, $posting->ledger_transaction_id);
        $this->assertSame(1_000, $posting->amount);
        $this->assertSame($event->id, $posting->dimensions['qualified_event_id']);

        // Une autre personne bénéficiaire ne remonte jamais dans cette
        // requête : la dimension isole bien les droits par personne, même
        // sur un compte `user_rights` mutualisé.
        $otherBeneficiary = $this->makeBeneficiary();
        $noCreditForOther = Posting::query()
            ->where('account_id', $userRightsAccountId)
            ->where('direction', PostingDirection::Credit)
            ->where('dimensions->beneficiary_person_account_link_id', $otherBeneficiary->id)
            ->count();
        $this->assertSame(0, $noCreditForOther);
    }
}
