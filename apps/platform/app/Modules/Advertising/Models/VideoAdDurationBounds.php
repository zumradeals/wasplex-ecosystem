<?php

namespace App\Modules\Advertising\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Bornes de durée (secondes) autorisées pour une vidéo publicitaire (Lot 4,
 * instruction explicite du fondateur 2026-07-30). Mirroir exact de
 * {@see AudienceSegmentSizeThreshold} : une seule ligne `active` à la fois,
 * versionnée, réglable par une seule personne habilitée
 * (`advertising.manage_video_duration_bounds`).
 *
 * @property string $id
 * @property int $min_seconds
 * @property int $max_seconds
 * @property int $version
 * @property string $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class VideoAdDurationBounds extends Model
{
    protected $table = 'advertising.video_ad_duration_bounds';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['min_seconds', 'max_seconds', 'version', 'state', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'min_seconds' => 'integer',
            'max_seconds' => 'integer',
            'version' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $bounds): void {
            $bounds->id ??= (string) Str::uuid7();
            $bounds->effective_from ??= now();
        });
    }
}
