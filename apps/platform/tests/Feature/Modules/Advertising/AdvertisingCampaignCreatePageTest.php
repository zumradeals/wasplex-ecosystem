<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Nouvelle campagne (UX-0001 §8) : n'affiche que des contraintes
 * réellement configurées (secteurs actifs, formats autorisés, seuil
 * minimal d'audience actif) — jamais un tarif par durée inventé.
 */
class AdvertisingCampaignCreatePageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/campaigns/create');

        $response->assertRedirect('/login');
    }

    public function test_a_representative_sees_active_sector_classifications_and_the_active_threshold(): void
    {
        $this->makeActiveSizeThreshold(500);
        $sector = $this->makeSectorClassification('retail');

        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $this->makeAdvertiserProfile($link);

        $response = $this->actingAs($user)->get('/advertising/campaigns/create');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/campaign-create')
            ->has('sectorClassifications', 1)
            ->where('sectorClassifications.0.id', $sector->id)
            ->where('sectorClassifications.0.allowed_formats', ['banner'])
            ->where('audienceSizeThreshold', 500),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.campaigns.create'));
    }
}
