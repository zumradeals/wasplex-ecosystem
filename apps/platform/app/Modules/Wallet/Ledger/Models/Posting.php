<?php

namespace App\Modules\Wallet\Ledger\Models;

use App\Modules\Wallet\Ledger\Enums\PostingDirection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Écriture élémentaire d'une transaction comptable (ADR-0003 §15). Montant
 * toujours entier et strictement positif ; le sens (débit/crédit) est
 * explicite, jamais déduit d'un signe. Immuable après création (voir
 * `postings_prevent_update` / `postings_prevent_delete`, ADR-0003 §11).
 *
 * @property string $id
 * @property string $ledger_transaction_id
 * @property string $account_id
 * @property PostingDirection $direction
 * @property int $amount
 * @property string $currency
 * @property array<string, mixed> $dimensions
 * @property string $label
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Posting extends Model
{
    protected $table = 'ledger.postings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ledger_transaction_id', 'account_id', 'direction', 'amount', 'currency', 'dimensions', 'label',
    ];

    protected function casts(): array
    {
        return [
            'direction' => PostingDirection::class,
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $posting): void {
            $posting->id ??= (string) Str::uuid7();
            $posting->dimensions ??= [];
        });
    }

    /**
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function dimensions(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
