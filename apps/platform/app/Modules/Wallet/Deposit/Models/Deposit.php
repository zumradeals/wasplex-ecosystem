<?php

namespace App\Modules\Wallet\Deposit\Models;

use App\Modules\Wallet\Deposit\Enums\DepositState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dépôt Wallet (AMD-0017 ; ecosystem/wallet/05). `amount` est la seule
 * source de vérité pour le WP finalement crédité (§3) : `fees_amount`/
 * `net_amount` ne servent qu'à la comptabilisation du côté charge/actif
 * de Wasplex, jamais à recalculer ce que la personne reçoit.
 *
 * @property string $id
 * @property string $person_id
 * @property string $initiated_by_person_account_link_id
 * @property DepositState $state
 * @property string $country_code
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
class Deposit extends Model
{
    protected $table = 'ledger.wallet_deposits';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * `id` est exceptionnellement mass-assignable : `DepositInitiationService`
     * doit connaître l'identifiant du dépôt avant même l'appel GeniusPay
     * (les URL de retour référencent ce dépôt) — jamais alimenté depuis une
     * entrée HTTP brute, toujours généré côté serveur par le contrôleur.
     */
    protected $fillable = [
        'id', 'person_id', 'initiated_by_person_account_link_id', 'state', 'country_code',
        'currency', 'amount', 'provider', 'provider_payment_id', 'provider_reference',
        'checkout_url', 'fees_amount', 'net_amount', 'idempotency_key',
        'ledger_transaction_id', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => DepositState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $deposit): void {
            $deposit->id ??= (string) Str::uuid7();
        });
    }
}
