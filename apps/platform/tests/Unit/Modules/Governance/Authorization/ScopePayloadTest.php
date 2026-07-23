<?php

namespace Tests\Unit\Modules\Governance\Authorization;

use App\Modules\Governance\Authorization\Enums\Environment;
use App\Modules\Governance\Authorization\Support\InvalidScopePayloadException;
use App\Modules\Governance\Authorization\Support\ScopePayload;
use PHPUnit\Framework\TestCase;

class ScopePayloadTest extends TestCase
{
    public function test_a_valid_minimal_payload_is_accepted(): void
    {
        $scope = ScopePayload::fromArray(['self' => true]);

        $this->assertTrue($scope->self);
        $this->assertNull($scope->organizationId);
    }

    public function test_an_empty_payload_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray([]);
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray(['unknown_dimension' => 'value']);
    }

    public function test_an_unknown_schema_version_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromStored(99, ['self' => true]);
    }

    public function test_a_wildcard_value_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray(['resource_type' => '*']);
    }

    public function test_the_unlimited_values_are_rejected(): void
    {
        foreach (['all', 'global', 'any', 'ALL'] as $value) {
            try {
                ScopePayload::fromArray(['resource_type' => $value]);
                $this->fail("\"{$value}\" should have been rejected as an unlimited value.");
            } catch (InvalidScopePayloadException $exception) {
                $this->assertStringContainsString('unlimited', $exception->getMessage());
            }
        }
    }

    public function test_a_payload_exceeding_the_maximum_size_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray(['resource_ids' => array_map(fn (int $i): string => str_repeat('x', 200).$i, range(1, 50))]);
    }

    public function test_a_list_exceeding_the_maximum_item_count_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray(['resource_ids' => array_map(fn (int $i): string => "id-{$i}", range(1, 51))]);
    }

    public function test_a_list_with_duplicates_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray(['resource_ids' => ['a', 'a']]);
    }

    public function test_an_unknown_environment_value_is_rejected(): void
    {
        $this->expectException(InvalidScopePayloadException::class);

        ScopePayload::fromArray(['environment' => 'not_a_real_environment']);
    }

    public function test_round_trip_through_to_array_preserves_declared_dimensions(): void
    {
        $original = ScopePayload::fromArray([
            'organization_id' => 'org-1',
            'resource_type' => 'invoice',
            'resource_ids' => ['invoice-1'],
            'country_code' => 'CI',
            'territory_codes' => ['CI-AB'],
            'environment' => Environment::Production->value,
            'fields' => ['name'],
        ]);

        $restored = ScopePayload::fromArray($original->toArray());

        $this->assertSame($original->organizationId, $restored->organizationId);
        $this->assertSame($original->resourceType, $restored->resourceType);
        $this->assertSame($original->resourceIds, $restored->resourceIds);
        $this->assertSame($original->countryCode, $restored->countryCode);
        $this->assertSame($original->territoryCodes, $restored->territoryCodes);
        $this->assertSame($original->environment, $restored->environment);
        $this->assertSame($original->fields, $restored->fields);
    }
}
