<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\BillingStatus;
use App\Modules\Advertising\Enums\FraudDecision;
use App\Modules\Advertising\Services\CampaignBudgetService;
use App\Modules\Wallet\Ledger\Models\LedgerTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * QualifiedEvent (ADR-0010 §3, `01-cycle-creation-valeur.md` §3-4). Seul
 * {@see CampaignBudgetService} écrit
 * dans cette table et dans le Ledger correspondant : jamais d'écriture
 * directe dans `ledger.*` depuis ce module (ADR-0010 §2).
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $campaign_version_id
 * @property string $format
 * @property array<string, mixed> $evidence
 * @property Carbon|CarbonImmutable $occurred_at
 * @property FraudDecision $fraud_decision
 * @property int $applied_price_amount
 * @property string $applied_price_currency
 * @property string|null $pricing_configuration_key
 * @property int|null $pricing_configuration_version
 * @property BillingStatus $billing_status
 * @property string $reservation_transaction_id
 * @property string|null $consumption_transaction_id
 * @property string|null $distribution_transaction_id
 * @property string|null $release_transaction_id
 * @property string $correlation_id
 * @property string $idempotency_key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class QualifiedEvent extends Model
{
    protected $table = 'advertising.qualified_events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'campaign_id', 'campaign_version_id', 'format', 'evidence', 'occurred_at',
        'fraud_decision', 'applied_price_amount', 'applied_price_currency',
        'pricing_configuration_key', 'pricing_configuration_version',
        'billing_status', 'reservation_transaction_id', 'consumption_transaction_id',
        'distribution_transaction_id', 'release_transaction_id',
        'correlation_id', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'fraud_decision' => FraudDecision::class,
            'applied_price_amount' => 'integer',
            'pricing_configuration_version' => 'integer',
            'billing_status' => BillingStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
            $event->fraud_decision ??= FraudDecision::None;
            $event->billing_status ??= BillingStatus::Pending;
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<CampaignVersion, $this>
     */
    public function campaignVersion(): BelongsTo
    {
        return $this->belongsTo(CampaignVersion::class, 'campaign_version_id');
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function reservationTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'reservation_transaction_id');
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function evidence(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }
}
