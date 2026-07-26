<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Services\AudienceSegmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Audiences (UX-0001 §8). La taille sous le seuil minimal actif ne doit
 * jamais être communiquée telle quelle (AMD-0009 §13) — voir
 * {@see AudienceSegmentGuard::retrievableSize()}.
 */
class AdvertisingAudiencesPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/audiences');

        $response->assertRedirect('/login');
    }

    public function test_a_representative_sees_the_estimated_size_of_its_own_segment(): void
    {
        $this->makeActiveSizeThreshold(500);

        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $version = $this->proposeAndApproveVersion($campaign, author: $link);

        app(AudienceSegmentGuard::class)->createSegment($version, ['country' => ['CI']], 10_000);

        $response = $this->actingAs($user)->get('/advertising/audiences');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/audiences')
            ->has('audiences', 1)
            ->where('audiences.0.campaign_id', $campaign->id)
            ->where('audiences.0.estimated_size', 10_000)
            ->where('audiences.0.below_threshold', false),
        );
    }

    public function test_a_segment_below_threshold_never_exposes_its_real_size(): void
    {
        $this->makeActiveSizeThreshold(500);

        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $version = $this->proposeAndApproveVersion($campaign, author: $link);

        app(AudienceSegmentGuard::class)->createSegment($version, ['country' => ['CI']], 10);

        $response = $this->actingAs($user)->get('/advertising/audiences');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('audiences', 1)
            ->where('audiences.0.estimated_size', null)
            ->where('audiences.0.below_threshold', true),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.audiences'));
    }
}
