<?php

namespace Tests\Feature\Modules\Advertising;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Organisation et accès (UX-0001 §8) : dossier annonceur du représentant
 * courant — même déclaration que {@see AdvertiserProfileController},
 * seulement sa propre destination de navigation.
 */
class AdvertisingOrganizationPageTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/advertising/organization');

        $response->assertRedirect('/login');
    }

    public function test_a_subject_with_no_advertiser_profile_sees_the_onboarding_state(): void
    {
        $user = $this->makeUser('no-profile-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($user)->get('/advertising/organization');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/organization')
            ->where('access.allowed', true)
            ->where('advertiserProfile', null),
        );
    }

    public function test_a_representative_sees_its_own_declared_profile(): void
    {
        $user = $this->makeUser('representative-'.Str::uuid().'@example.com');
        $user->forceFill(['email_verified_at' => now()])->save();
        $link = $this->activeLinkFor($user);
        $advertiser = $this->makeAdvertiserProfile($link);

        $response = $this->actingAs($user)->get('/advertising/organization');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('advertising/organization')
            ->where('advertiserProfile.id', $advertiser->id)
            ->where('advertiserProfile.legal_name', $advertiser->legal_name)
            ->where('advertiserProfile.country_code', $advertiser->country_code),
        );
    }

    public function test_the_route_is_registered(): void
    {
        $this->assertTrue(Route::has('advertising.organization'));
    }
}
