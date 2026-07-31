<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\AdvertiserWalletDepositState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dépôt en libre-service dans le solde annonceur mutualisé via GeniusPay
 * (instruction explicite du fondateur, 2026-07-31). Mirroir exact de
 * {@see CampaignFunding} : `amount` est le montant demandé par l'annonceur,
 * seule source de vérité pour le crédit final — `fees_amount`/`net_amount`
 * ne sont connus qu'après réponse GeniusPay et jamais utilisés pour
 * recalculer le montant crédité.
 *
 * @property string $id
 * @property string $advertiser_profile_id
 * @property string $initiated_by_person_account_link_id
 * @property AdvertiserWalletDepositState $state
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
class AdvertiserWalletDeposit extends Model
{
    protected $table = 'advertising.advertiser_wallet_deposits';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * `id` est exceptionnellement mass-assignable : `AdvertiserWalletDepositInitiationService`
     * doit connaître l'identifiant du dépôt avant même l'appel GeniusPay
     * (les URL de retour référencent ce dépôt précis).
     */
    protected $fillable = [
        'id', 'advertiser_profile_id', 'initiated_by_person_account_link_id', 'state',
        'currency', 'amount', 'provider', 'provider_payment_id', 'provider_reference',
        'checkout_url', 'fees_amount', 'net_amount', 'idempotency_key',
        'ledger_transaction_id', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => AdvertiserWalletDepositState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $deposit): void {
            $deposit->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<AdvertiserProfile, $this>
     */
    public function advertiserProfile(): BelongsTo
    {
        return $this->belongsTo(AdvertiserProfile::class, 'advertiser_profile_id');
    }
}
