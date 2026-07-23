<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\PolicyState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Version de politique appliquée par une décision d'autorisation
 * (ADR-0002, ADR-0004 §10). Jamais éditée rétroactivement.
 *
 * @property string $id
 * @property string $stable_key
 * @property int $version
 * @property PolicyState $state
 * @property string $checksum
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PolicyVersion extends Model
{
    protected $table = 'governance.policy_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['stable_key', 'version', 'state', 'checksum', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'state' => PolicyState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $policy): void {
            $policy->id ??= (string) Str::uuid7();
            $policy->effective_from ??= now();
        });
    }
}
