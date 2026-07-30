<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\CampaignFundingState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Financement de campagne en libre-service via GeniusPay (véto du dirigeant,
 * 2026-07-30). Mirroir exact de `App\Modules\Wallet\Deposit\Models\Deposit` :
 * `amount` est le montant demandé par l'annonceur, seule source de vérité
 * pour le crédit final — `fees_amount`/`net_amount` ne sont connus qu'après
 * réponse GeniusPay et jamais utilisés pour recalculer le montant crédité.
 *
 * @property string $id
 * @property string $campaign_id
 * @property string $initiated_by_person_account_link_id
 * @property CampaignFundingState $state
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
class CampaignFunding extends Model
{
    protected $table = 'advertising.campaign_fundings';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * `id` est exceptionnellement mass-assignable : `CampaignFundingInitiationService`
     * doit connaître l'identifiant du financement avant même l'appel
     * GeniusPay (les URL de retour référencent ce financement précis).
     */
    protected $fillable = [
        'id', 'campaign_id', 'initiated_by_person_account_link_id', 'state',
        'currency', 'amount', 'provider', 'provider_payment_id', 'provider_reference',
        'checkout_url', 'fees_amount', 'net_amount', 'idempotency_key',
        'ledger_transaction_id', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => CampaignFundingState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $funding): void {
            $funding->id ??= (string) Str::uuid7();
        });
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}
