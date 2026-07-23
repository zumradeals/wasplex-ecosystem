<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\LedgerTransactionState;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Cas nominal (ADR-0003 §17 "équilibre de chaque transaction") : une
 * transaction équilibrée, même devise, est comptabilisée avec succès.
 */
class BalancedTransactionTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_a_balanced_transaction_is_posted_successfully(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('balanced');

        $intent = $this->debitCreditIntent($debitAccount, $creditAccount, 1_000);

        $transaction = $this->poster()->post($intent);

        $this->assertInstanceOf(LedgerTransaction::class, $transaction);
        $this->assertSame(LedgerTransactionState::Posted, $transaction->state);
        $this->assertCount(2, $transaction->postings);

        $debitPosting = $transaction->postings->firstWhere('account_id', $debitAccount->id);
        $creditPosting = $transaction->postings->firstWhere('account_id', $creditAccount->id);

        $this->assertSame(PostingDirection::Debit, $debitPosting->direction);
        $this->assertSame(1_000, $debitPosting->amount);
        $this->assertSame(PostingDirection::Credit, $creditPosting->direction);
        $this->assertSame(1_000, $creditPosting->amount);

        $this->assertDatabaseCount('ledger.ledger_transactions', 1);
        $this->assertDatabaseCount('ledger.postings', 2);
    }

    public function test_a_transaction_with_more_than_two_postings_can_balance(): void
    {
        $source = $this->makeAccount('coverage.multi_source', AccountNature::Asset, AccountPurpose::Coverage);
        $userRights = $this->makeAccount('user_rights.multi_a', AccountNature::Liability, AccountPurpose::UserRights);
        $revenue = $this->makeAccount('wasplex_own_resources.multi_b', AccountNature::Revenue, AccountPurpose::WasplexOwnResources);

        $intent = $this->makeIntent(postings: [
            new PostingLine($source->id, PostingDirection::Debit, 1_000, 'XOF', 'Encaissement'),
            new PostingLine($userRights->id, PostingDirection::Credit, 500, 'XOF', 'Part utilisateur'),
            new PostingLine($revenue->id, PostingDirection::Credit, 500, 'XOF', 'Part Wasplex'),
        ]);

        $transaction = $this->poster()->post($intent);

        $this->assertCount(3, $transaction->postings);
    }
}
