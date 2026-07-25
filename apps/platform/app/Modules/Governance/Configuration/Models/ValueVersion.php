<?php

namespace App\Modules\Governance\Configuration\Models;

use App\Modules\Governance\Configuration\Enums\ValueVersionState;
use App\Modules\Governance\Configuration\Services\ConfigurationValueManager;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * ValueVersion (ADR-0002 §8) : valeur immuable proposée pour une
 * `Definition`, avec sa justification. Le contenu (`value`, `justification`)
 * n'est librement composable qu'à l'état `draft` — voir le déclencheur
 * `value_versions_prevent_semantic_mutation`, appliqué en défense en
 * profondeur de {@see ConfigurationValueManager}.
 *
 * @property string $id
 * @property string $definition_id
 * @property int $version
 * @property mixed $value
 * @property string $justification
 * @property ValueVersionState $state
 * @property string $author_person_account_link_id
 * @property Carbon|CarbonImmutable|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon|CarbonImmutable|null $replaced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ValueVersion extends Model
{
    protected $table = 'configuration.value_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'definition_id', 'version', 'value', 'justification', 'state',
        'author_person_account_link_id', 'rejected_at', 'rejection_reason', 'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'state' => ValueVersionState::class,
            'rejected_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<Definition, $this>
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(Definition::class, 'definition_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'author_person_account_link_id');
    }

    /**
     * @return HasMany<Approval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'value_version_id');
    }

    /**
     * @return HasOne<Activation, $this>
     */
    public function activation(): HasOne
    {
        return $this->hasOne(Activation::class, 'value_version_id');
    }

    /**
     * `value` n'est jamais contraint à un objet JSON (contrairement à
     * `Definition::constraints()`) : un scalaire (entier, texte, booléen)
     * est un contenu parfaitement valide et le cas le plus courant
     * (ADR-0002 §3.6 : « des paramètres typés », pas des structures libres).
     *
     * @return Attribute<mixed, mixed>
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): mixed => $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR),
            set: fn (mixed $value): string => json_encode($value, JSON_THROW_ON_ERROR),
        );
    }
}
