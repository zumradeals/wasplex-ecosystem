<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\PurposeState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Définition d'une finalité autorisée (ADR-0004 §8).
 *
 * @property string $id
 * @property string $stable_key
 * @property int $version
 * @property string $description
 * @property PurposeState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PurposeDefinition extends Model
{
    protected $table = 'governance.purpose_definitions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['stable_key', 'version', 'description', 'state', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'state' => PurposeState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $definition): void {
            $definition->id ??= (string) Str::uuid7();
            $definition->effective_from ??= now();
        });
    }
}
