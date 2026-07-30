<?php

namespace App\Modules\Wallet\Deposit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Clés API du prestataire de dépôt Wallet, configurables par le personnel
 * habilité (`wallet_deposit.manage_credentials`, véto du dirigeant
 * 2026-07-30). `api_key`/`api_secret`/`webhook_secret` utilisent le cast
 * Eloquent `encrypted` : la colonne ne porte jamais la valeur en clair, sa
 * lisibilité dépend uniquement d'`APP_KEY` (jamais versionné). `base_url`
 * n'est pas un secret.
 *
 * @property string $id
 * @property string $provider
 * @property string|null $base_url
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property string|null $webhook_secret
 * @property string|null $updated_by_person_account_link_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ProviderCredential extends Model
{
    protected $table = 'ledger.wallet_deposit_provider_credentials';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'provider', 'base_url', 'api_key', 'api_secret', 'webhook_secret',
        'updated_by_person_account_link_id',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $credential): void {
            $credential->id ??= (string) Str::uuid7();
        });
    }
}
