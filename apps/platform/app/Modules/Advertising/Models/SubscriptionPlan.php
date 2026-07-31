<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\ConfigurationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Plan d'abonnement (instruction explicite du fondateur, 2026-07-31 ;
 * docs/02 §2). Rattache exactement un type économique — l'achat de ce plan
 * est le chemin normal vers ce type (voir `EconomicTypeResolver`).
 *
 * @property string $id
 * @property string $stable_key
 * @property string $name
 * @property int $version
 * @property int $price_amount
 * @property string $currency
 * @property int $duration_days
 * @property string $economic_type_id
 * @property ConfigurationState $state
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SubscriptionPlan extends Model
{
    protected $table = 'advertising.subscription_plans';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'stable_key', 'name', 'version', 'price_amount', 'currency',
        'duration_days', 'economic_type_id', 'state', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'price_amount' => 'integer',
            'duration_days' => 'integer',
            'state' => ConfigurationState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            $plan->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<EconomicType, $this>
     */
    public function economicType(): BelongsTo
    {
        return $this->belongsTo(EconomicType::class, 'economic_type_id');
    }
}
