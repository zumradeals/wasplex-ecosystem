<?php

namespace App\Modules\Wallet\Ledger\Models;

use App\Modules\Wallet\Ledger\Enums\AccountNature;
use App\Modules\Wallet\Ledger\Enums\AccountPurpose;
use App\Modules\Wallet\Ledger\Enums\AccountStatus;
use App\Modules\Wallet\Ledger\Projections\AccountBalanceProjection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Compte du plan de comptes (ADR-0003 §4, §15). N'expose jamais de champ de
 * solde : le solde courant est une projection reconstruite depuis
 * `ledger.postings` par {@see AccountBalanceProjection}
 * (ADR-0003 §19 : « aucun solde n'est une donnée modifiable »).
 *
 * @property string $id
 * @property string $code
 * @property AccountNature $nature
 * @property AccountPurpose $purpose
 * @property string $legal_entity
 * @property string $country_code
 * @property string $currency
 * @property string $module
 * @property string|null $compartment
 * @property AccountStatus $status
 * @property array<string, mixed> $movement_restrictions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Account extends Model
{
    protected $table = 'ledger.accounts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'nature', 'purpose', 'legal_entity', 'country_code',
        'currency', 'module', 'compartment', 'status', 'movement_restrictions',
    ];

    protected function casts(): array
    {
        return [
            'nature' => AccountNature::class,
            'purpose' => AccountPurpose::class,
            'status' => AccountStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            $account->id ??= (string) Str::uuid7();
            $account->movement_restrictions ??= [];
        });
    }

    /**
     * Encode toujours en objet JSON, y compris lorsque le tableau PHP est
     * vide, pour respecter la contrainte PostgreSQL
     * `jsonb_typeof(movement_restrictions) = 'object'` (même choix que
     * `Grant::scopePayload()`).
     *
     * @return Attribute<array<string, mixed>, array<string, mixed>>
     */
    protected function movementRestrictions(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value === null ? [] : json_decode($value, true),
            set: fn (array $value): string => json_encode($value, JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR),
        );
    }
}
