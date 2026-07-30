<?php

namespace Tests\Feature\Modules\Advertising\Http\Admin;

use App\Modules\Advertising\Enums\CampaignFundingState;
use App\Modules\Advertising\Models\CampaignFunding;
use App\Modules\Advertising\Models\CampaignFundingWebhookEvent;
use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Models\AuthorizationDecisionRecord;
use App\Modules\Identity\Enums\LinkOrigin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Modules\Advertising\AdvertisingTestCase;

/**
 * Financements de campagne — supervision (véto du dirigeant, 2026-07-30) :
 * gouverné par `campaign_funding.review`, lecture seule. Mirroir de
 * `Tests\Feature\Modules\Wallet\Deposit\Http\Admin\AdminWalletDepositRouteTest`.
 */
class AdminCampaignFundingRouteTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/campaign-fundings');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_without_the_capability_sees_the_denied_state(): void
    {
        $user = $this->makeUser('no-staff-grant-'.Str::uuid().'@example.com', LinkOrigin::Migration);
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/admin/campaign-fundings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaign-fundings')
            ->where('disputedFundings.access.allowed', false)
            ->where('disputedFundings.access.reason', 'no_active_grant')
            ->where('disputedFundings.items', [])
            ->where('invalidWebhooks.access.allowed', false)
            ->where('invalidWebhooks.items', []),
        );

        $this->assertTrue(
            AuthorizationDecisionRecord::query()
                ->where('capability_key', 'campaign_funding.review')
                ->where('operation', 'read')
                ->where('decision', AuthorizationDecision::Denied->value)
                ->where('reason_code', 'no_active_grant')
                ->exists(),
        );
    }

    public function test_a_holder_of_campaign_funding_review_sees_empty_queues_by_default(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign_funding.review', 'advertising.campaign');

        $response = $this->actingAs($staff->user)->get('/admin/campaign-fundings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaign-fundings')
            ->where('disputedFundings.access.allowed', true)
            ->where('disputedFundings.items', [])
            ->where('invalidWebhooks.access.allowed', true)
            ->where('invalidWebhooks.items', []),
        );
    }

    public function test_a_holder_of_campaign_funding_review_sees_a_disputed_funding_but_not_a_completed_one(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign_funding.review', 'advertising.campaign');

        $representative = $this->makeRepresentative();
        $campaign = $this->makeCampaign($this->makeAdvertiserProfile($representative));

        $disputed = CampaignFunding::create([
            'campaign_id' => $campaign->id,
            'initiated_by_person_account_link_id' => $representative->id,
            'state' => CampaignFundingState::UnknownReconciliation,
            'currency' => 'XOF',
            'amount' => 5000,
            'provider' => 'geniuspay',
            'provider_reference' => 'ref-'.Str::uuid(),
            'idempotency_key' => 'idem-'.Str::uuid(),
        ]);

        CampaignFunding::create([
            'campaign_id' => $campaign->id,
            'initiated_by_person_account_link_id' => $representative->id,
            'state' => CampaignFundingState::Completed,
            'currency' => 'XOF',
            'amount' => 3000,
            'provider' => 'geniuspay',
            'provider_reference' => 'ref-'.Str::uuid(),
            'idempotency_key' => 'idem-'.Str::uuid(),
        ]);

        $response = $this->actingAs($staff->user)->get('/admin/campaign-fundings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaign-fundings')
            ->has('disputedFundings.items', 1)
            ->where('disputedFundings.items.0.campaign_funding_id', $disputed->id)
            ->where('disputedFundings.items.0.amount', 5000),
        );
    }

    public function test_a_holder_of_campaign_funding_review_sees_an_invalid_signature_webhook(): void
    {
        $staff = $this->makeRepresentative();
        $this->grantStaffCapability($staff, 'campaign_funding.review', 'advertising.campaign');

        $invalid = CampaignFundingWebhookEvent::create([
            'provider' => 'geniuspay',
            'event_type' => 'payment.success',
            'signature_valid' => false,
            'raw_payload' => '{}',
            'received_at' => now(),
        ]);

        CampaignFundingWebhookEvent::create([
            'provider' => 'geniuspay',
            'event_type' => 'payment.success',
            'signature_valid' => true,
            'raw_payload' => '{}',
            'received_at' => now(),
        ]);

        $response = $this->actingAs($staff->user)->get('/admin/campaign-fundings');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/campaign-fundings')
            ->has('invalidWebhooks.items', 1)
            ->where('invalidWebhooks.items.0.webhook_event_id', $invalid->id),
        );
    }

    public function test_the_admin_campaign_fundings_route_is_registered(): void
    {
        $this->assertTrue(Route::has('admin.campaign-fundings'));
    }
}
