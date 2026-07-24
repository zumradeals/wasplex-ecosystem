<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\CampaignState;
use App\Modules\Advertising\Projections\CampaignBudgetProjection;
use App\Modules\Wallet\Ledger\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Identité stable d'une campagne et son état global (ADR-0010 §3). Le
 * budget n'est jamais une colonne ici : voir
 * {@see CampaignBudgetProjection},
 * qui reconstruit disponible/réservé/consommé depuis les trois comptes
 * référencés ci-dessous.
 *
 * @property string $id
 * @property string $advertiser_profile_id
 * @property string $code
 * @property string $currency
 * @property CampaignState $state
 * @property string $available_account_id
 * @property string $reserved_account_id
 * @property string $consumed_account_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Campaign extends Model
{
    protected $table = 'advertising.campaigns';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'advertiser_profile_id', 'code', 'currency', 'state',
        'available_account_id', 'reserved_account_id', 'consumed_account_id',
    ];

    protected function casts(): array
    {
        return [
            'state' => CampaignState::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            $campaign->id ??= (string) Str::uuid7();
            $campaign->state ??= CampaignState::Active;
        });
    }

    /**
     * @return BelongsTo<AdvertiserProfile, $this>
     */
    public function advertiserProfile(): BelongsTo
    {
        return $this->belongsTo(AdvertiserProfile::class, 'advertiser_profile_id');
    }

    /**
     * @return HasMany<CampaignVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CampaignVersion::class, 'campaign_id');
    }

    /**
     * @return HasMany<ModerationCase, $this>
     */
    public function moderationCases(): HasMany
    {
        return $this->hasMany(ModerationCase::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function availableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'available_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function reservedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reserved_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function consumedAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'consumed_account_id');
    }
}
