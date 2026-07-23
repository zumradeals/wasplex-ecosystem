<?php

namespace Tests\Feature\Modules\Wallet\Ledger;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * ADR-0003 §1, §8, §15, §19 : le solde d'un compte n'est jamais une donnée
 * stockée, seulement une projection reconstructible depuis les postings.
 */
class BalanceProjectionTest extends LedgerTestCase
{
    use RefreshDatabase;

    public function test_the_account_balance_matches_the_sum_of_its_postings(): void
    {
        $coverage = $this->makeAccount('coverage.projection', AccountNature::Asset, AccountPurpose::Coverage);
        $userRights = $this->makeAccount('user_rights.projection', AccountNature::Liability, AccountPurpose::UserRights);

        $this->poster()->post($this->debitCreditIntent($coverage, $userRights, 5_000));
        $this->poster()->post($this->debitCreditIntent($coverage, $userRights, 2_500));
        $this->poster()->post($this->debitCreditIntent($userRights, $coverage, 1_000));

        $projection = app(AccountBalanceProjection::class);

        // Reconstruction manuelle, indépendante de la classe testée : lit
        // directement les postings, sans passer par AccountBalanceProjection.
        $coverageRaw = DB::table('ledger.postings')
            ->where('account_id', $coverage->id)
            ->selectRaw("SUM(CASE WHEN direction = 'debit' THEN amount ELSE -amount END) as net")
            ->value('net');

        $userRightsRaw = DB::table('ledger.postings')
            ->where('account_id', $userRights->id)
            ->selectRaw("SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) as net")
            ->value('net');

        $this->assertSame((int) $coverageRaw, $projection->currentBalance($coverage->fresh()));
        $this->assertSame((int) $userRightsRaw, $projection->currentBalance($userRights->fresh()));
        $this->assertSame(6_500, $projection->currentBalance($coverage->fresh()));
        $this->assertSame(6_500, $projection->currentBalance($userRights->fresh()));
    }

    public function test_balance_follows_the_natural_debit_credit_convention_by_nature(): void
    {
        $asset = $this->makeAccount('coverage.nature_asset', AccountNature::Asset, AccountPurpose::Coverage);
        $liability = $this->makeAccount('user_rights.nature_liability', AccountNature::Liability, AccountPurpose::UserRights);
        $revenue = $this->makeAccount('wasplex_own_resources.nature_revenue', AccountNature::Revenue, AccountPurpose::WasplexOwnResources);
        $expense = $this->makeAccount('tax_and_fees.nature_expense', AccountNature::Expense, AccountPurpose::TaxAndFees);

        // Actif financé par un passif : l'actif croît au débit, le passif au crédit.
        $this->poster()->post($this->debitCreditIntent($asset, $liability, 10_000));
        // Charge financée par une baisse d'actif : la charge croît au débit.
        $this->poster()->post($this->debitCreditIntent($expense, $asset, 3_000));
        // Revenu constaté contre le passif : le revenu croît au crédit.
        $this->poster()->post($this->debitCreditIntent($liability, $revenue, 1_500));

        $projection = app(AccountBalanceProjection::class);

        $this->assertSame(10_000 - 3_000, $projection->currentBalance($asset->fresh()));
        $this->assertSame(10_000 - 1_500, $projection->currentBalance($liability->fresh()));
        $this->assertSame(1_500, $projection->currentBalance($revenue->fresh()));
        $this->assertSame(3_000, $projection->currentBalance($expense->fresh()));
    }

    public function test_balance_is_zero_for_an_account_with_no_postings(): void
    {
        $account = $this->makeAccount('coverage.empty', AccountNature::Asset, AccountPurpose::Coverage);

        $this->assertSame(0, app(AccountBalanceProjection::class)->currentBalance($account));
    }
}
