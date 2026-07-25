<?php

namespace App\Modules\Governance\Configuration\Models;

use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Activation (ADR-0002 §8) : preuve de mise en effet d'une `ValueVersion`
 * approuvée, y compris la version qu'elle a remplacée le cas échéant.
 * Jamais modifiée ni supprimée après création — voir les déclencheurs
 * `activations_prevent_update`/`activations_prevent_deletion`.
 *
 * @property string $id
 * @property string $value_version_id
 * @property string $activated_by_person_account_link_id
 * @property Carbon|CarbonImmutable $activated_at
 * @property string $correlation_id
 * @property string|null $replaced_value_version_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Activation extends Model
{
    protected $table = 'configuration.activations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'value_version_id', 'activated_by_person_account_link_id', 'activated_at',
        'correlation_id', 'replaced_value_version_id',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $activation): void {
            $activation->id ??= (string) Str::uuid7();
            $activation->activated_at ??= now();
        });
    }

    /**
     * @return BelongsTo<ValueVersion, $this>
     */
    public function valueVersion(): BelongsTo
    {
        return $this->belongsTo(ValueVersion::class, 'value_version_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'activated_by_person_account_link_id');
    }

    /**
     * @return BelongsTo<ValueVersion, $this>
     */
    public function replacedValueVersion(): BelongsTo
    {
        return $this->belongsTo(ValueVersion::class, 'replaced_value_version_id');
    }
}
