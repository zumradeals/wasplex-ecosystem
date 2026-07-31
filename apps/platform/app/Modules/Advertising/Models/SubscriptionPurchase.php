<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\SubscriptionPurchaseState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Achat d'un abonnement via GeniusPay (instruction explicite du fondateur,
 * 2026-07-31). Mirroir exact de {@see AdvertiserWalletDeposit}.
 *
 * @property string $id
 * @property string $person_id
 * @property string $subscription_plan_id
 * @property string $initiated_by_person_account_link_id
 * @property SubscriptionPurchaseState $state
 * @property string $currency
 * @property int $amount
 * @property string $provider
 * @property string|null $provider_payment_id
 * @property string|null $provider_reference
 * @property string|null $checkout_url
 * @property int|null $fees_amount
 * @property int|null $net_amount
 * @property string $idempotency_key
 * @property string|null $ledger_transaction_id
 * @property string|null $failure_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SubscriptionPurchase extends Model
{
    protected $table = 'advertising.subscription_purchases';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'person_id', 'subscription_plan_id', 'initiated_by_person_account_link_id', 'state',
        'currency', 'amount', 'provider', 'provider_payment_id', 'provider_reference',
        'checkout_url', 'fees_amount', 'net_amount', 'idempotency_key',
        'ledger_transaction_id', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => SubscriptionPurchaseState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $purchase): void {
            $purchase->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
