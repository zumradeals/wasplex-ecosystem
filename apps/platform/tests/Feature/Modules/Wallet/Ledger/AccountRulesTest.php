<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use App\Modules\Wallet\Ledger\Services\PostingLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Déclencheur `enforce_posting_account_rules` (ADR-0003 §1, §4) : un compte
 * n'accepte qu'un posting de sa propre devise, seulement s'il est actif, et
 * seulement dans le sens que ses restrictions de mouvement autorisent.
 */
class AccountRulesTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_a_posting_currency_must_match_its_account_currency(): void
    {
        $xofAccount = $this->makeAccount('coverage.rules_currency', AccountNature::Asset, AccountPurpose::Coverage, 'XOF');
        $otherAccount = $this->makeAccount('user_rights.rules_currency', AccountNature::Liability, AccountPurpose::UserRights, 'XOF');

        $intent = $this->makeIntent(postings: [
            new PostingLine($xofAccount->id, PostingDirection::Debit, 1_000, 'EUR', 'Devise incohérente'),
            new PostingLine($otherAccount->id, PostingDirection::Credit, 1_000, 'EUR', 'Crédit'),
        ]);

        $this->expectException(QueryException::class);

        $this->poster()->post($intent);
    }

    public function test_a_frozen_account_refuses_any_new_posting(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('rules_frozen');
        $debitAccount->forceFill(['status' => AccountStatus::Frozen])->save();

        $this->expectException(QueryException::class);

        $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));
    }

    public function test_a_closed_account_refuses_any_new_posting(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('rules_closed');
        $creditAccount->forceFill(['status' => AccountStatus::Closed])->save();

        $this->expectException(QueryException::class);

        $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));
    }

    public function test_a_debit_restricted_account_refuses_a_debit_posting(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('rules_debit_restricted');
        $debitAccount->forceFill(['movement_restrictions' => ['debit_allowed' => false]])->save();

        $this->expectException(QueryException::class);

        $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));
    }

    public function test_a_debit_restricted_account_still_accepts_a_credit_posting(): void
    {
        [$otherAccount, $debitRestrictedAccount] = $this->makeAccountPair('rules_debit_restricted_credit_ok');
        $debitRestrictedAccount->forceFill(['movement_restrictions' => ['debit_allowed' => false]])->save();

        // Ici, le compte restreint est crédité, jamais débité : autorisé.
        $transaction = $this->poster()->post($this->debitCreditIntent($otherAccount, $debitRestrictedAccount, 1_000));

        $this->assertNotNull($transaction->id);
    }

    public function test_a_credit_restricted_account_refuses_a_credit_posting(): void
    {
        [$debitAccount, $creditAccount] = $this->makeAccountPair('rules_credit_restricted');
        $creditAccount->forceFill(['movement_restrictions' => ['credit_allowed' => false]])->save();

        $this->expectException(QueryException::class);

        $this->poster()->post($this->debitCreditIntent($debitAccount, $creditAccount, 1_000));
    }
}
