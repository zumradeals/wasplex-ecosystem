<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Models\PersonAdvertisingProfile;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use App\Modules\Identity\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ciblage réel + estimation d'audience (Lot 3, véto du dirigeant) :
 * `AudienceSegmentGuard::computeSize()`/`estimateForPreview()` — un calcul
 * réel depuis le profil publicitaire consenti, jamais une déclaration de
 * l'annonceur.
 */
class AudienceSegmentGuardEstimationTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    private function makeProfile(array $attributes): PersonAdvertisingProfile
    {
        return PersonAdvertisingProfile::create(array_merge([
            'person_id' => Person::create()->id,
            'country_code' => null,
            'city' => null,
            'neighborhood' => null,
            'age_bracket' => null,
            'gender' => null,
            'interests' => [],
            'consent_version' => 1,
            'consent_given_at' => now(),
        ], $attributes));
    }

    public function test_it_counts_only_profiles_matching_every_specified_dimension(): void
    {
        $this->makeProfile(['country_code' => 'CI', 'age_bracket' => '25-34', 'gender' => 'woman']);
        $this->makeProfile(['country_code' => 'CI', 'age_bracket' => '35-44', 'gender' => 'woman']);
        $this->makeProfile(['country_code' => 'SN', 'age_bracket' => '25-34', 'gender' => 'woman']);

        $guard = app(AudienceSegmentGuard::class);

        $this->assertSame(1, $guard->computeSize([
            'country' => ['CI'],
            'age_bracket' => ['25-34'],
            'gender' => ['woman'],
        ]));
    }

    public function test_it_matches_city_and_neighborhood_case_insensitively(): void
    {
        $this->makeProfile(['city' => 'Abidjan', 'neighborhood' => 'Abobo']);
        $this->makeProfile(['city' => 'Abidjan', 'neighborhood' => 'Cocody']);

        $guard = app(AudienceSegmentGuard::class);

        $this->assertSame(1, $guard->computeSize(['city' => 'abidjan', 'neighborhood' => 'ABOBO']));
        $this->assertSame(2, $guard->computeSize(['city' => 'Abidjan']));
    }

    public function test_interests_match_on_any_requested_code_not_all(): void
    {
        $this->makeProfile(['interests' => ['sport']]);
        $this->makeProfile(['interests' => ['fashion']]);
        $this->makeProfile(['interests' => ['sport', 'fashion']]);
        $this->makeProfile(['interests' => ['cooking']]);

        $guard = app(AudienceSegmentGuard::class);

        $this->assertSame(3, $guard->computeSize(['interests' => ['sport', 'fashion']]));
    }

    public function test_a_withdrawn_profile_is_never_counted(): void
    {
        $this->makeProfile(['country_code' => 'CI', 'consent_withdrawn_at' => null]);
        $this->makeProfile(['country_code' => null, 'consent_withdrawn_at' => now()]);

        $guard = app(AudienceSegmentGuard::class);

        $this->assertSame(1, $guard->computeSize(['country' => ['CI']]));
    }

    public function test_an_unrecognized_criterion_key_is_silently_ignored_not_rejected(): void
    {
        $this->makeProfile(['country_code' => 'CI']);

        $guard = app(AudienceSegmentGuard::class);

        $this->assertSame(1, $guard->computeSize(['country' => ['CI'], 'made_up_key' => 'anything']));
    }

    public function test_no_criteria_at_all_counts_every_profile(): void
    {
        $this->makeProfile(['country_code' => 'CI']);
        $this->makeProfile(['country_code' => 'SN']);

        $guard = app(AudienceSegmentGuard::class);

        $this->assertSame(2, $guard->computeSize([]));
    }

    public function test_estimate_for_preview_masks_a_result_below_the_active_threshold(): void
    {
        $this->makeActiveSizeThreshold(5);
        $this->makeProfile(['country_code' => 'CI']);
        $this->makeProfile(['country_code' => 'CI']);

        $result = app(AudienceSegmentGuard::class)->estimateForPreview(['country' => ['CI']]);

        $this->assertTrue($result['below_threshold']);
        $this->assertNull($result['estimated_size']);
    }

    public function test_estimate_for_preview_returns_the_exact_size_at_or_above_the_threshold(): void
    {
        $this->makeActiveSizeThreshold(2);
        $this->makeProfile(['country_code' => 'CI']);
        $this->makeProfile(['country_code' => 'CI']);

        $result = app(AudienceSegmentGuard::class)->estimateForPreview(['country' => ['CI']]);

        $this->assertFalse($result['below_threshold']);
        $this->assertSame(2, $result['estimated_size']);
    }
}
