<?php

namespace App\Modules\Governance\Authorization\Support;

use App\Modules\Governance\Authorization\Enums\Environment;

/**
 * Portée structurée d'un grant, schéma version 1 (P003-B1 §10).
 *
 * Un payload est toujours un objet JSON borné (8 Ko maximum), sans joker,
 * sans clé inconnue, avec au moins une restriction explicite. Une version
 * inconnue ou un format inconnu entraîne systématiquement un refus fermé
 * (via {@see InvalidScopePayloadException}), jamais une portée illimitée.
 */
final readonly class ScopePayload
{
    public const SCHEMA_VERSION = 1;

    private const MAX_PAYLOAD_BYTES = 8192;

    private const MAX_LIST_ITEMS = 50;

    /**
     * @var list<string>
     */
    private const ALLOWED_KEYS = [
        'self', 'organization_id', 'resource_type', 'resource_ids',
        'country_code', 'territory_codes', 'environment', 'fields',
    ];

    /**
     * @var list<string>
     */
    private const FORBIDDEN_VALUES = ['*', 'all', 'global', 'any'];

    private function __construct(
        public ?bool $self,
        public ?string $organizationId,
        public ?string $resourceType,
        /** @var list<string>|null */
        public ?array $resourceIds,
        public ?string $countryCode,
        /** @var list<string>|null */
        public ?array $territoryCodes,
        public ?Environment $environment,
        /** @var list<string>|null */
        public ?array $fields,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromStored(int $schemaVersion, array $payload): self
    {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidScopePayloadException("unsupported scope_schema_version: {$schemaVersion}");
        }

        return self::fromArray($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $encoded = json_encode($payload);

        if ($encoded === false || strlen($encoded) > self::MAX_PAYLOAD_BYTES) {
            throw new InvalidScopePayloadException('scope payload exceeds the maximum allowed size');
        }

        $unknownKeys = array_diff(array_keys($payload), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidScopePayloadException('scope payload contains unknown keys: '.implode(',', $unknownKeys));
        }

        if ($payload === []) {
            throw new InvalidScopePayloadException('scope payload requires at least one explicit restriction');
        }

        $self = self::readBool($payload, 'self');
        $organizationId = self::readString($payload, 'organization_id');
        $resourceType = self::readString($payload, 'resource_type');
        $resourceIds = self::readList($payload, 'resource_ids');
        $countryCode = self::readString($payload, 'country_code');
        $territoryCodes = self::readList($payload, 'territory_codes');
        $fields = self::readList($payload, 'fields');

        $environment = null;

        if (array_key_exists('environment', $payload)) {
            if (! is_string($payload['environment'])) {
                throw new InvalidScopePayloadException('scope payload "environment" must be a string');
            }

            $environment = Environment::tryFrom($payload['environment']);

            if ($environment === null) {
                throw new InvalidScopePayloadException('scope payload "environment" is not a known value');
            }
        }

        return new self(
            self: $self,
            organizationId: $organizationId,
            resourceType: $resourceType,
            resourceIds: $resourceIds,
            countryCode: $countryCode,
            territoryCodes: $territoryCodes,
            environment: $environment,
            fields: $fields,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'self' => $this->self,
            'organization_id' => $this->organizationId,
            'resource_type' => $this->resourceType,
            'resource_ids' => $this->resourceIds,
            'country_code' => $this->countryCode,
            'territory_codes' => $this->territoryCodes,
            'environment' => $this->environment?->value,
            'fields' => $this->fields,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function readBool(array $payload, string $key): ?bool
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        if (! is_bool($payload[$key])) {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" must be a boolean");
        }

        return $payload[$key];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function readString(array $payload, string $key): ?string
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        if (! is_string($value) || $value === '') {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" must be a non-empty string");
        }

        self::assertNotWildcard($key, $value);

        return $value;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>|null
     */
    private static function readList(array $payload, string $key): ?array
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" must be a list");
        }

        if ($value === []) {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" must not be empty when present");
        }

        if (count($value) > self::MAX_LIST_ITEMS) {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" exceeds the maximum allowed items");
        }

        if (count($value) !== count(array_unique($value))) {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" must not contain duplicates");
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw new InvalidScopePayloadException("scope payload \"{$key}\" must only contain non-empty strings");
            }

            self::assertNotWildcard($key, $item);
        }

        return $value;
    }

    private static function assertNotWildcard(string $key, string $value): void
    {
        if (str_contains($value, '*') || in_array(strtolower($value), self::FORBIDDEN_VALUES, true)) {
            throw new InvalidScopePayloadException("scope payload \"{$key}\" must not use a wildcard or an unlimited value");
        }
    }
}
