<?php

namespace Tests\Feature\Modules\Governance\Configuration;

use App\Models\User;
use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use App\Modules\Governance\Configuration\Models\Definition;
use App\Modules\Identity\Models\PersonAccountLink;
use App\Modules\Identity\Services\RegistersUserIdentity;
use Tests\TestCase;

abstract class ConfigurationTestCase extends TestCase
{
    protected function makeUser(string $email): User
    {
        return app(RegistersUserIdentity::class)->register([
            'name' => 'Utilisateur '.$email,
            'email' => $email,
            'password' => 'password',
        ]);
    }

    protected function activeLinkFor(User $user): PersonAccountLink
    {
        return PersonAccountLink::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    protected function makeLink(string $email): PersonAccountLink
    {
        return $this->activeLinkFor($this->makeUser($email));
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    protected function makeDefinition(
        string $stableKey = 'sample.test_parameter',
        ConfigurationLevel $level = ConfigurationLevel::C2,
        ValueType $valueType = ValueType::Integer,
        array $constraints = [],
        int $version = 1,
    ): Definition {
        return Definition::create([
            'stable_key' => $stableKey,
            'version' => $version,
            'domain' => 'sample',
            'level' => $level,
            'value_type' => $valueType,
            'unit' => null,
            'constraints' => $constraints,
            'description' => 'Paramètre de test.',
            'state' => DefinitionState::Active,
        ]);
    }
}
