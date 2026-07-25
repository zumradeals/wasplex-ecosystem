<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Wallet\Balance\Services\PersonLedgerAccounts;
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
 *
 * Depuis P006-A, le crédit `user_rights` atterrit directement sur le
 * compte Ledger individuel du bénéficiaire ({@see PersonLedgerAccounts}),
 * plus sur un compte mutualisé par devise : ce test vérifie donc
 * l'isolement par personne au niveau du compte lui-même, pas seulement par
 * une dimension applicative sur un compte partagé.
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

        $beneficiaryAccountId = app(PersonLedgerAccounts::class)->available($beneficiary->person_id, $campaign->currency)->id;

        // Retrouvable directement sur le compte individuel du bénéficiaire
        // (P006-A) : la dimension reste portée par le posting pour
        // identifier l'événement qualifié précis, mais l'isolement par
        // personne est désormais garanti par le compte lui-même.
        $creditToBeneficiary = Posting::query()
            ->where('account_id', $beneficiaryAccountId)
            ->where('direction', PostingDirection::Credit)
            ->get();

        $this->assertCount(1, $creditToBeneficiary);
        $posting = $creditToBeneficiary->first();
        $this->assertSame($accepted->distribution_transaction_id, $posting->ledger_transaction_id);
        $this->assertSame(1_000, $posting->amount);
        $this->assertSame($event->id, $posting->dimensions['qualified_event_id']);
        $this->assertSame($beneficiary->id, $posting->dimensions['beneficiary_person_account_link_id']);

        // Une autre personne bénéficiaire possède son propre compte, jamais
        // crédité par l'événement ci-dessus : l'isolement ne dépend plus
        // d'une dimension applicative sur un compte partagé.
        $otherBeneficiary = $this->makeBeneficiary();
        $otherAccountId = app(PersonLedgerAccounts::class)->available($otherBeneficiary->person_id, $campaign->currency)->id;
        $this->assertNotSame($beneficiaryAccountId, $otherAccountId);
        $noCreditForOther = Posting::query()
            ->where('account_id', $otherAccountId)
            ->where('direction', PostingDirection::Credit)
            ->count();
        $this->assertSame(0, $noCreditForOther);
    }
}
