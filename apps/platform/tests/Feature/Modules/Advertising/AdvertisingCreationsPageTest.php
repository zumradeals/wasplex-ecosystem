<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Créations (UX-0001 §8) : contenu publicitaire de chaque
 * `CampaignVersion` du dossier annonceur.
 */
class AdvertisingCreationsPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/creations');

        $response->assertRedirect('/login');
    }

    public function test_a_representative_sees_only_its_own_creations(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);
        $campaign = $this->makeCampaign($advertiser);
        $this->proposeAndApproveVersion($campaign, sector: $this->makeSectorClassification('retail'), author: $link);

        // Une autre annonceuse ne doit jamais apparaître dans cette liste.
        $otherCampaign = $this->makeCampaign($this->makeAdvertiserProfile());
        $this->proposeAndApproveVersion($otherCampaign, sector: $this->makeSectorClassification('finance'));

        $response = $this->actingAs($user)->get('/advertising/creations');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/creations')
            ->has('creations', 1)
            ->where('creations.0.campaign_id', $campaign->id)
            ->where('creations.0.headline', 'Titre de test')
            ->where('creations.0.format', 'banner')
            ->where('creations.0.destination_url', 'https://annonceur.example.com'),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.creations'));
    }
}
