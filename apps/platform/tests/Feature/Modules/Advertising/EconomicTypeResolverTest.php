<?php

namespace Tests\Feature\Modules\Advertising;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Models\EconomicType;
use App\Modules\Advertising\Models\PersonEconomicTypeAssignment;
use App\Modules\Advertising\Models\PersonSubscription;
use App\Modules\Advertising\Models\SubscriptionPlan;
use App\Modules\Advertising\Services\EconomicTypeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

/**
 * Priorité de résolution du type économique (instruction explicite du
 * fondateur, 2026-07-31 ; docs/02 §3) : affectation manuelle > abonnement
 * actif > type par défaut. La migration `2026_07_31_200002` seede déjà un
 * type par défaut actif — chaque test le retire d'abord pour contrôler
 * précisément la configuration en jeu.
 */
class EconomicTypeResolverTest extends AdvertisingTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        EconomicType::query()->update(['state' => 'retired']);
    }

    private function makeEconomicType(array $overrides = []): EconomicType
    {
        return EconomicType::create(array_merge([
            'stable_key' => 'test-'.uniqid(),
            'name' => 'Type de test',
            'version' => 1,
            'user_share_percentage' => 100,
            'monthly_quota' => null,
            'is_default' => false,
            'state' => ConfigurationState::Active,
        ], $overrides));
    }

    public function test_a_person_without_assignment_or_subscription_receives_the_default_type(): void
    {
        $default = $this->makeEconomicType(['stable_key' => 'default-type', 'is_default' => true]);
        $person = $this->makeBeneficiary();

        $resolved = app(EconomicTypeResolver::class)->forPerson($person->person_id);

        $this->assertSame($default->id, $resolved->id);
    }

    public function test_no_active_default_type_fails_closed(): void
    {
        $person = $this->makeBeneficiary();

        $this->expectException(RuntimeException::class);

        app(EconomicTypeResolver::class)->forPerson($person->person_id);
    }

    public function test_an_active_subscription_overrides_the_default_type(): void
    {
        $this->makeEconomicType(['stable_key' => 'default-type', 'is_default' => true]);
        $premium = $this->makeEconomicType(['stable_key' => 'premium']);
        $plan = SubscriptionPlan::create([
            'stable_key' => 'premium-plan',
            'name' => 'Plan premium',
            'version' => 1,
            'price_amount' => 5000,
            'currency' => 'XOF',
            'duration_days' => 30,
            'economic_type_id' => $premium->id,
            'state' => ConfigurationState::Active,
        ]);
        $person = $this->makeBeneficiary();
        PersonSubscription::create([
            'person_id' => $person->person_id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $resolved = app(EconomicTypeResolver::class)->forPerson($person->person_id);

        $this->assertSame($premium->id, $resolved->id);
    }

    public function test_an_expired_subscription_falls_back_to_the_default_type(): void
    {
        $default = $this->makeEconomicType(['stable_key' => 'default-type', 'is_default' => true]);
        $premium = $this->makeEconomicType(['stable_key' => 'premium']);
        $plan = SubscriptionPlan::create([
            'stable_key' => 'premium-plan',
            'name' => 'Plan premium',
            'version' => 1,
            'price_amount' => 5000,
            'currency' => 'XOF',
            'duration_days' => 30,
            'economic_type_id' => $premium->id,
            'state' => ConfigurationState::Active,
        ]);
        $person = $this->makeBeneficiary();
        PersonSubscription::create([
            'person_id' => $person->person_id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
        ]);

        $resolved = app(EconomicTypeResolver::class)->forPerson($person->person_id);

        $this->assertSame($default->id, $resolved->id);
    }

    public function test_a_manual_assignment_overrides_an_active_subscription(): void
    {
        $this->makeEconomicType(['stable_key' => 'default-type', 'is_default' => true]);
        $premium = $this->makeEconomicType(['stable_key' => 'premium']);
        $overridden = $this->makeEconomicType(['stable_key' => 'overridden']);
        $plan = SubscriptionPlan::create([
            'stable_key' => 'premium-plan',
            'name' => 'Plan premium',
            'version' => 1,
            'price_amount' => 5000,
            'currency' => 'XOF',
            'duration_days' => 30,
            'economic_type_id' => $premium->id,
            'state' => ConfigurationState::Active,
        ]);
        $person = $this->makeBeneficiary();
        PersonSubscription::create([
            'person_id' => $person->person_id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
        PersonEconomicTypeAssignment::create([
            'person_id' => $person->person_id,
            'economic_type_id' => $overridden->id,
        ]);

        $resolved = app(EconomicTypeResolver::class)->forPerson($person->person_id);

        $this->assertSame($overridden->id, $resolved->id);
    }

    public function test_a_manual_assignment_pointing_to_a_retired_type_falls_back_to_the_default(): void
    {
        $default = $this->makeEconomicType(['stable_key' => 'default-type', 'is_default' => true]);
        $retired = $this->makeEconomicType(['stable_key' => 'retired-type', 'state' => ConfigurationState::Retired]);
        $person = $this->makeBeneficiary();
        PersonEconomicTypeAssignment::create([
            'person_id' => $person->person_id,
            'economic_type_id' => $retired->id,
        ]);

        $resolved = app(EconomicTypeResolver::class)->forPerson($person->person_id);

        $this->assertSame($default->id, $resolved->id);
    }
}
