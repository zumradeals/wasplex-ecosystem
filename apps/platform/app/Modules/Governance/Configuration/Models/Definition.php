<?php

namespace App\Modules\Governance\Configuration\Models;

use App\Modules\Governance\Configuration\Enums\ConfigurationLevel;
use App\Modules\Governance\Configuration\Enums\DefinitionState;
use App\Modules\Governance\Configuration\Enums\ValueType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Definition (ADR-0002 §8) : clé stable, domaine propriétaire, type, unité,
 * niveau C1-C3, contraintes et documentation d'un paramètre configurable.
 * Régie par ADR-0002 : jamais codée en dur, jamais réécrite — une nouvelle
 * version remplace la précédente sans effacer l'historique.
 *
 * @property string $id
 * @property string $stable_key
 * @property int $version
 * @property string $domain
 * @property ConfigurationLevel $level
 * @property ValueType $value_type
 * @property string|null $unit
 * @property array<string, mixed> $constraints
 * @property string $description
 * @property DefinitionState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Definition extends Model
{
    protected $table = 'configuration.definitions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stable_key', 'version', 'domain', 'level', 'value_type', 'unit',
        'constraints', 'description', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'level' => ConfigurationLevel::class,
            'value_type' => ValueType::class,
            'state' => DefinitionState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            $definition->id ??= (string) Str::uuid7();
            $definition->constraints ??= [];
            $definition->effective_from ??= now();
        });
    }

    /**
     * @return HasMany<ValueVersion, $this>
     */
    public function valueVersions(): HasMany
    {
        return $this->hasMany(ValueVersion::class, 'definition_id');
    }

    /**
     * Encode toujours en objet JSON, y compris pour un tableau PHP vide,
     * afin de respecter `jsonb_typeof(constraints) = 'object'` (même choix
     * que `SectorClassification::frequencyRules()`).
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function constraints(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }
}
