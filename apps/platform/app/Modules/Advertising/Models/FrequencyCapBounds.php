<?php

namespace App\Modules\Advertising\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Bornes de revisionnage gratuit (instruction explicite du fondateur,
 * 2026-07-31 : « récompensé une seule fois... peut revoir gratuitement
 * au maximum 3 fois par jour », 10 au total). Mirroir exact de
 * {@see VideoAdDurationBounds} : une seule ligne `active` à la fois,
 * versionnée, réglable par une seule personne habilitée
 * (`advertising.manage_frequency_cap`).
 *
 * @property string $id
 * @property int $daily_free_view_limit
 * @property int $lifetime_free_view_limit
 * @property int $version
 * @property string $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FrequencyCapBounds extends Model
{
    protected $table = 'advertising.frequency_cap_bounds';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'daily_free_view_limit', 'lifetime_free_view_limit', 'version', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'daily_free_view_limit' => 'integer',
            'lifetime_free_view_limit' => 'integer',
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
