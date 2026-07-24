<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\ConfigurationState;
use App\Modules\Advertising\Services\AudienceSegmentGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seuil minimal de taille d'un segment d'audience, versionné (AMD-0009
 * §13, ADR-0010 §3). Une seule ligne `active` à la fois — voir
 * {@see AudienceSegmentGuard}.
 *
 * @property string $id
 * @property int $version
 * @property int $minimum_size
 * @property ConfigurationState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AudienceSegmentSizeThreshold extends Model
{
    protected $table = 'advertising.audience_segment_size_thresholds';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['version', 'minimum_size', 'state', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'minimum_size' => 'integer',
            'state' => ConfigurationState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $threshold): void {
            $threshold->id ??= (string) Str::uuid7();
            $threshold->effective_from ??= now();
        });
    }
}
