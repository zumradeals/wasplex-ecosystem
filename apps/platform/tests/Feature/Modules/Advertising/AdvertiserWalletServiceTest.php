<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Projections\AdvertiserWalletBalanceProjection;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Advertising\Services\AdvertiserWalletService;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Advertising\Services\Exceptions\InsufficientBudgetException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

/**
 * Solde annonceur mutualisé (instruction explicite du fondateur, 2026-07-31)
 * : dépôt et allocation, câblés directement sur `LedgerPoster` comme
 * {@see CampaignBudgetService} — mirroir
 * de style de `CampaignBudgetCycleTest`.
 */
class AdvertiserWalletServiceTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function service(): AdvertiserWalletService
    {
        return app(AdvertiserWalletService::class);
    }

    private function balances(): AdvertiserWalletBalanceProjection
    {
        return app(AdvertiserWalletBalanceProjection::class);
    }

    public function test_a_deposit_credits_the_advertiser_wallet_balance(): void
    {
        $advertiser = $this->makeAdvertiserProfile();

        $this->service()->deposit($advertiser, 'XOF', 10000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $balances = $this->balances()->forAdvertiser($advertiser->fresh());
        $xof = collect($balances)->firstWhere('currency', 'XOF');

        $this->assertSame(10000, $xof['available']);
    }

    public function test_an_advertiser_without_any_deposit_has_no_balance_line(): void
    {
        $advertiser = $this->makeAdvertiserProfile();

        $this->assertSame([], $this->balances()->forAdvertiser($advertiser));
    }

    public function test_allocation_moves_the_amount_from_the_wallet_to_the_campaign_budget(): void
    {
        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser, 'XOF');
        $this->service()->deposit($advertiser, 'XOF', 10000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $this->service()->allocateToCampaign($advertiser, $campaign, 4000, 'allocation-'.Str::uuid(), (string) Str::uuid());

        $walletBalance = collect($this->balances()->forAdvertiser($advertiser->fresh()))->firstWhere('currency', 'XOF');
        $this->assertSame(6000, $walletBalance['available']);

        $campaignAvailable = app(CampaignBudgetProjection::class)->available($campaign->fresh());
        $this->assertSame(4000, $campaignAvailable);
    }

    public function test_allocating_more_than_the_wallet_balance_is_rejected(): void
    {
        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser, 'XOF');
        $this->service()->deposit($advertiser, 'XOF', 1000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $this->expectException(InsufficientBudgetException::class);

        $this->service()->allocateToCampaign($advertiser, $campaign, 1001, 'allocation-'.Str::uuid(), (string) Str::uuid());
    }

    public function test_allocating_with_no_deposit_at_all_is_rejected(): void
    {
        $advertiser = $this->makeAdvertiserProfile();
        $campaign = $this->makeCampaign($advertiser, 'XOF');

        $this->expectException(InsufficientBudgetException::class);

        $this->service()->allocateToCampaign($advertiser, $campaign, 1, 'allocation-'.Str::uuid(), (string) Str::uuid());
    }

    public function test_replaying_the_same_deposit_idempotency_key_never_double_credits(): void
    {
        $advertiser = $this->makeAdvertiserProfile();
        $reference = 'deposit-'.Str::uuid();

        $this->service()->deposit($advertiser, 'XOF', 5000, $reference, (string) Str::uuid());
        $this->service()->deposit($advertiser, 'XOF', 5000, $reference, (string) Str::uuid());

        $balance = collect($this->balances()->forAdvertiser($advertiser->fresh()))->firstWhere('currency', 'XOF');
        $this->assertSame(5000, $balance['available']);
    }

    public function test_a_wallet_balance_in_a_different_currency_is_never_used_for_allocation(): void
    {
        $advertiser = $this->makeAdvertiserProfile();
        $xofCampaign = $this->makeCampaign($advertiser, 'XOF');
        $this->service()->deposit($advertiser, 'EUR', 10000, 'deposit-'.Str::uuid(), (string) Str::uuid());

        $this->expectException(InsufficientBudgetException::class);

        $this->service()->allocateToCampaign($advertiser, $xofCampaign, 1, 'allocation-'.Str::uuid(), (string) Str::uuid());
    }
}
