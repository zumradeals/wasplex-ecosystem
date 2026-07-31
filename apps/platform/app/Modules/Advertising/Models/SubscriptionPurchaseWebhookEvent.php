<?php

namespace App\Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Entrée d'inbox d'un webhook GeniusPay pour l'achat d'abonnement (mirroir
 * exact de {@see AdvertiserWalletDepositWebhookEvent}).
 *
 * @property string $id
 * @property string $provider
 * @property string|null $event_type
 * @property bool $signature_valid
 * @property string $raw_payload
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property string|null $processing_result
 * @property string|null $subscription_purchase_id
 */
class SubscriptionPurchaseWebhookEvent extends Model
{
    protected $table = 'advertising.subscription_purchase_webhook_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'provider', 'event_type', 'signature_valid', 'raw_payload',
        'received_at', 'processed_at', 'processing_result', 'subscription_purchase_id',
    ];

    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
        });
    }
}
