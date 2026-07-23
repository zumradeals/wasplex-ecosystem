<?php

namespace App\Modules\Governance\Authorization\Support;

use App\Modules\Identity\Enums\ContactAssurance;
use App\Modules\Identity\Enums\IdentityAssurance;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\SessionAssurance;
use App\Modules\Identity\Enums\UniquenessAssurance;

/**
 * Conditions structurées d'un grant, schéma version 1 (P003-B1 §11).
 *
 * Un niveau absent n'est jamais interprété comme implicitement maximal : il
 * signifie simplement qu'aucune contrainte supplémentaire n'est posée sur
 * cet axe par ce grant. Une version inconnue ou un format inconnu entraîne
 * systématiquement un refus fermé (via {@see InvalidConditionsPayloadException}).
 */
final readonly class ConditionsPayload
{
    public const SCHEMA_VERSION = 1;

    private const MAX_PAYLOAD_BYTES = 8192;

    /**
     * @var list<string>
     */
    private const ALLOWED_KEYS = [
        'minimum_contact_assurance',
        'minimum_identity_assurance',
        'minimum_uniqueness_assurance',
        'minimum_session_assurance',
        'required_organization_status',
    ];

    private function __construct(
        public ?ContactAssurance $minimumContactAssurance,
        public ?IdentityAssurance $minimumIdentityAssurance,
        public ?UniquenessAssurance $minimumUniquenessAssurance,
        public ?SessionAssurance $minimumSessionAssurance,
        public ?OrganizationStatus $requiredOrganizationStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromStored(int $schemaVersion, array $payload): self
    {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidConditionsPayloadException("unsupported conditions_schema_version: {$schemaVersion}");
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
            throw new InvalidConditionsPayloadException('conditions payload exceeds the maximum allowed size');
        }

        $unknownKeys = array_diff(array_keys($payload), self::ALLOWED_KEYS);

        if ($unknownKeys !== []) {
            throw new InvalidConditionsPayloadException('conditions payload contains unknown keys: '.implode(',', $unknownKeys));
        }

        return new self(
            minimumContactAssurance: self::readEnum($payload, 'minimum_contact_assurance', ContactAssurance::class),
            minimumIdentityAssurance: self::readEnum($payload, 'minimum_identity_assurance', IdentityAssurance::class),
            minimumUniquenessAssurance: self::readEnum($payload, 'minimum_uniqueness_assurance', UniquenessAssurance::class),
            minimumSessionAssurance: self::readEnum($payload, 'minimum_session_assurance', SessionAssurance::class),
            requiredOrganizationStatus: self::readEnum($payload, 'required_organization_status', OrganizationStatus::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'minimum_contact_assurance' => $this->minimumContactAssurance?->value,
            'minimum_identity_assurance' => $this->minimumIdentityAssurance?->value,
            'minimum_uniqueness_assurance' => $this->minimumUniquenessAssurance?->value,
            'minimum_session_assurance' => $this->minimumSessionAssurance?->value,
            'required_organization_status' => $this->requiredOrganizationStatus?->value,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  array<string, mixed>  $payload
     * @param  class-string<T>  $enumClass
     * @return T|null
     */
    private static function readEnum(array $payload, string $key, string $enumClass): mixed
    {
        if (! array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];

        if (! is_string($value)) {
            throw new InvalidConditionsPayloadException("conditions payload \"{$key}\" must be a string");
        }

        $case = $enumClass::tryFrom($value);

        if ($case === null) {
            throw new InvalidConditionsPayloadException("conditions payload \"{$key}\" is not a known value");
        }

        return $case;
    }
}
