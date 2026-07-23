<?php

namespace App\Modules\Wallet\Ledger\Models;

use App\Modules\Wallet\Ledger\Enums\LedgerTransactionState;
use App\Modules\Wallet\Ledger\Services\LedgerPoster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Transaction comptable (ADR-0003 §15). Une fois créée, aucune colonne
 * n'est plus jamais modifiée : voir les déclencheurs
 * `ledger_transactions_prevent_update` / `..._prevent_delete`
 * (ADR-0003 §11). Seule {@see LedgerPoster}
 * écrit dans cette table — aucun module ne poste jamais directement
 * (ADR-0003 §7, §14).
 *
 * @property string $id
 * @property string $type
 * @property LedgerTransactionState $state
 * @property Carbon $business_date
 * @property Carbon $accounting_date
 * @property string $source_module
 * @property string $source_reference
 * @property string|null $configuration_key
 * @property int|null $configuration_version
 * @property string $idempotency_scope
 * @property string $idempotency_key
 * @property string $idempotency_fingerprint
 * @property string $correlation_id
 * @property string $authored_by
 * @property string|null $evidence_reference
 * @property string|null $reverses_transaction_id
 * @property string|null $reversal_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LedgerTransaction extends Model
{
    protected $table = 'ledger.ledger_transactions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type', 'state', 'business_date', 'accounting_date',
        'source_module', 'source_reference',
        'configuration_key', 'configuration_version',
        'idempotency_scope', 'idempotency_key', 'idempotency_fingerprint',
        'correlation_id', 'authored_by', 'evidence_reference',
        'reverses_transaction_id', 'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => LedgerTransactionState::class,
            'business_date' => 'date',
            'accounting_date' => 'date',
            'configuration_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->id ??= (string) Str::uuid7();
            $transaction->state ??= LedgerTransactionState::Posted;
        });
    }

    /**
     * @return HasMany<Posting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class, 'ledger_transaction_id');
    }

    /**
     * @return BelongsTo<LedgerTransaction, $this>
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    /**
     * @return HasMany<LedgerTransaction, $this>
     */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_transaction_id');
    }

    public function isReversal(): bool
    {
        return $this->reverses_transaction_id !== null;
    }
}
