<?php

namespace Tests\Feature;

use App\Modules\Advertising\Models\InterestTaxonomyEntry;
use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StaffCapabilityTesting;
use Tests\TestCase;

/**
 * « Intérêts publicitaires » (véto du dirigeant, 2026-07-30 ; AMD-0009,
 * UX-0001 §11) — consentement facultatif, spécifique, versionné et
 * révocable sur `advertising.person_advertising_profiles`.
 */
class AdvertisingProfileRouteTest extends TestCase
{
    use RefreshDatabase;
    use StaffCapabilityTesting;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/me/advertising-profile');

        $response->assertRedirect('/login');
    }

    public function test_the_page_renders_no_profile_by_default(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->get('/me/advertising-profile');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('account/advertising-profile')
            ->where('profile', null)
            ->where('interestTaxonomy', []),
        );
    }

    public function test_the_page_lists_only_active_interest_taxonomy_entries(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);
        InterestTaxonomyEntry::create(['code' => 'retired-one', 'label' => 'Retiré', 'state' => 'retired']);

        $response = $this->actingAs($user)->get('/me/advertising-profile');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('account/advertising-profile')
            ->has('interestTaxonomy', 1)
            ->where('interestTaxonomy.0.code', 'sport'),
        );
    }

    public function test_granting_consent_stores_the_profile_versioned_and_dated(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);

        $response = $this->actingAs($user)->postJson('/me/advertising-profile', [
            'country_code' => 'CI',
            'city' => 'Abidjan',
            'neighborhood' => 'Abobo',
            'age_bracket' => '25-34',
            'gender' => 'woman',
            'interests' => ['sport'],
        ]);

        $response->assertOk();
        $response->assertJson([
            'consented' => true,
            'country_code' => 'CI',
            'city' => 'Abidjan',
            'neighborhood' => 'Abobo',
            'age_bracket' => '25-34',
            'gender' => 'woman',
            'interests' => ['sport'],
        ]);
        $response->assertJsonStructure(['consent_given_at']);

        $profile = PersonAdvertisingProfile::query()->sole();
        $this->assertSame(2, $profile->consent_version);
        $this->assertNotNull($profile->consent_given_at);
        $this->assertNull($profile->consent_withdrawn_at);
    }

    public function test_an_unknown_interest_code_is_rejected(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');

        $response = $this->actingAs($user)->postJson('/me/advertising-profile', [
            'age_bracket' => null,
            'gender' => null,
            'interests' => ['code-invente'],
        ]);

        $response->assertStatus(422);
        $this->assertSame('unknown_interest_code', $response->json('reason'));
        $this->assertDatabaseCount('advertising.person_advertising_profiles', 0);
    }

    public function test_a_retired_interest_code_is_rejected(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'retired']);

        $response = $this->actingAs($user)->postJson('/me/advertising-profile', [
            'age_bracket' => null,
            'gender' => null,
            'interests' => ['sport'],
        ]);

        $response->assertStatus(422);
    }

    public function test_withdrawing_consent_erases_the_stored_values(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');
        InterestTaxonomyEntry::create(['code' => 'sport', 'label' => 'Sport', 'state' => 'active']);

        $this->actingAs($user)->postJson('/me/advertising-profile', [
            'country_code' => 'CI',
            'city' => 'Abidjan',
            'neighborhood' => 'Abobo',
            'age_bracket' => '25-34',
            'gender' => 'woman',
            'interests' => ['sport'],
        ])->assertOk();

        $response = $this->actingAs($user)->postJson('/me/advertising-profile/withdraw', []);

        $response->assertOk();
        $response->assertJson(['profile' => null]);

        $profile = PersonAdvertisingProfile::query()->sole();
        $this->assertNull($profile->country_code);
        $this->assertNull($profile->city);
        $this->assertNull($profile->neighborhood);
        $this->assertNull($profile->age_bracket);
        $this->assertNull($profile->gender);
        $this->assertSame([], $profile->interests);
        $this->assertNotNull($profile->consent_withdrawn_at);
    }

    public function test_updating_after_withdrawal_grants_a_fresh_consent(): void
    {
        $user = $this->makeUser('advertiser-'.Str::uuid().'@example.com');

        $this->actingAs($user)->postJson('/me/advertising-profile', [
            'age_bracket' => '25-34',
            'gender' => null,
            'interests' => [],
        ])->assertOk();

        $this->actingAs($user)->postJson('/me/advertising-profile/withdraw', [])->assertOk();

        $response = $this->actingAs($user)->postJson('/me/advertising-profile', [
            'age_bracket' => '35-44',
            'gender' => null,
            'interests' => [],
        ]);

        $response->assertOk();
        $response->assertJson(['age_bracket' => '35-44']);

        $profile = PersonAdvertisingProfile::query()->sole();
        $this->assertNull($profile->consent_withdrawn_at);
    }

    public function test_the_advertising_profile_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('account.advertising-profile'));
        $this->assertTrue(Route::has('account.advertising-profile.update'));
        $this->assertTrue(Route::has('account.advertising-profile.withdraw'));
    }
}
