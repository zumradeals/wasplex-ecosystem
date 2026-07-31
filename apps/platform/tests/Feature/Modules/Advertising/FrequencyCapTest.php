<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Models\Campaign;
use App\Modules\Advertising\Models\CampaignVersion;
use App\Modules\Advertising\Models\FrequencyCapBounds;
use App\Modules\Advertising\Models\QualifiedEvent;
use App\Modules\Advertising\Services\Exceptions\FrequencyCapExceededException;
use App\Modules\Advertising\Services\FrequencyCapGuard;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Plafond de revisionnage gratuit (instruction explicite du fondateur,
 * 2026-07-31) : une personne n'est jamais récompensée deux fois pour la
 * même `CampaignVersion` — automatique, tracé par
 * `QualifiedEvent::$already_rewarded` — puis peut la revoir gratuitement
 * jusqu'aux bornes {@see FrequencyCapBounds} (3/jour, 10 au total par
 * défaut), au-delà desquelles toute nouvelle soumission est refusée.
 */
class FrequencyCapTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function submitAndAccept(
        Campaign $campaign,
        CampaignVersion $version,
        PersonAccountLink $beneficiary,
        int $amount = 1_000,
    ): QualifiedEvent {
        $event = $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $beneficiary,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: $amount,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );

        return $this->budgetService()->acceptQualifiedEvent($event);
    }

    public function test_the_first_engagement_pays_the_full_standard_share(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $viewer = $this->makeBeneficiary();

        $accepted = $this->submitAndAccept($campaign, $version, $viewer);

        $this->assertSame(500, $accepted->user_share_amount);
        $this->assertFalse($accepted->already_rewarded);
    }

    public function test_a_second_engagement_for_the_same_person_and_ad_pays_nothing_but_is_still_accepted(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $viewer = $this->makeBeneficiary();

        $this->submitAndAccept($campaign, $version, $viewer);
        $second = $this->submitAndAccept($campaign, $version, $viewer);

        $this->assertSame(BillingStatus::Accepted, $second->billing_status);
        $this->assertSame(0, $second->user_share_amount);
        $this->assertTrue($second->already_rewarded);

        // Wasplex absorbe l'intégralité du montant du revisionnage — la
        // publicité reste une exposition réelle facturée normalement à
        // l'annonceur.
        $this->assertDatabaseHas('ledger.postings', [
            'ledger_transaction_id' => $second->distribution_transaction_id,
            'amount' => 1_000,
            'direction' => 'credit',
        ]);
    }

    public function test_a_different_persons_first_engagement_on_the_same_ad_still_pays_full_share(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $first = $this->makeBeneficiary();
        $second = $this->makeBeneficiary();

        $this->submitAndAccept($campaign, $version, $first);
        $accepted = $this->submitAndAccept($campaign, $version, $second);

        $this->assertSame(500, $accepted->user_share_amount);
        $this->assertFalse($accepted->already_rewarded);
    }

    public function test_the_daily_cap_refuses_a_new_submission_the_same_day(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $viewer = $this->makeBeneficiary();

        // Borne par défaut : 3 vues gratuites par jour.
        $this->submitAndAccept($campaign, $version, $viewer);
        $this->submitAndAccept($campaign, $version, $viewer);
        $this->submitAndAccept($campaign, $version, $viewer);

        $this->expectException(FrequencyCapExceededException::class);

        $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $viewer,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_the_lifetime_cap_refuses_a_submission_even_on_a_new_day(): void
    {
        $campaign = $this->makeCampaign();
        $this->fundCampaign($campaign, 10_000);
        $version = $this->proposeAndApproveVersion($campaign);
        $viewer = $this->makeBeneficiary();

        // Simule un historique de 10 vues déjà acceptées (la borne par
        // défaut), chacune reportée à un jour distinct dans le passé
        // immédiatement après son acceptation — jamais deux le même jour,
        // pour ne jamais toucher le plafond quotidien : seul le plafond
        // total doit refuser ici.
        for ($i = 0; $i < 10; $i++) {
            $accepted = $this->submitAndAccept($campaign, $version, $viewer);
            $accepted->forceFill(['occurred_at' => now()->subDays($i + 1)])->save();
        }

        $this->expectException(FrequencyCapExceededException::class);

        $this->budgetService()->submitQualifiedEvent(
            campaign: $campaign,
            version: $version,
            beneficiary: $viewer,
            format: 'banner',
            evidence: ['proof' => 'completion'],
            appliedPriceAmount: 1_000,
            idempotencyKey: (string) Str::uuid(),
            correlationId: (string) Str::uuid(),
        );
    }

    public function test_the_guard_reports_no_cap_reached_below_the_bounds(): void
    {
        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);
        $viewer = $this->makeBeneficiary();

        $guard = app(FrequencyCapGuard::class);

        $this->assertFalse($guard->hasReachedCap($viewer->id, $version->id));
    }

    public function test_no_active_bounds_fails_closed(): void
    {
        FrequencyCapBounds::query()->update(['state' => 'retired']);

        $campaign = $this->makeCampaign();
        $version = $this->proposeAndApproveVersion($campaign);
        $viewer = $this->makeBeneficiary();

        $this->expectException(RuntimeException::class);

        app(FrequencyCapGuard::class)->hasReachedCap($viewer->id, $version->id);
    }
}
