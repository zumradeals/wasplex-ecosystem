<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\ConfigurationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Type économique (instruction explicite du fondateur, 2026-07-31 ;
 * docs/02-abonnements-et-types-utilisateurs.md §3). `name` est le seul nom
 * public — jamais codé comme règle technique, toujours administrable
 * (docs/02 §3). `stable_key` reste interne, jamais affiché.
 *
 * @property string $id
 * @property string $stable_key
 * @property string $name
 * @property int $version
 * @property int $user_share_percentage
 * @property int|null $monthly_quota
 * @property bool $is_default
 * @property ConfigurationState $state
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EconomicType extends Model
{
    protected $table = 'advertising.economic_types';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stable_key', 'name', 'version', 'user_share_percentage',
        'monthly_quota', 'is_default', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'user_share_percentage' => 'integer',
            'monthly_quota' => 'integer',
            'is_default' => 'boolean',
            'state' => ConfigurationState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $type): void {
            $type->id ??= (string) Str::uuid7();
        });
    }
}
