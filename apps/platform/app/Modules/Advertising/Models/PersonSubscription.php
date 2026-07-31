<?php

namespace App\Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Abonnement courant d'une personne (instruction explicite du fondateur,
 * 2026-07-31). Une seule ligne par personne — voir la migration de
 * création pour le raisonnement complet sur l'expiration lue à la volée
 * (`ends_at`, jamais un sweep périodique).
 *
 * @property string $id
 * @property string $person_id
 * @property string $subscription_plan_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PersonSubscription extends Model
{
    protected $table = 'advertising.person_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['person_id', 'subscription_plan_id', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            $subscription->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isActive(): bool
    {
        return $this->ends_at->isFuture();
    }
}
