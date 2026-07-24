<?php

namespace App\Modules\Advertising\Models;

use App\Modules\Advertising\Enums\AdvertiserProfileStatus;
use App\Modules\Identity\Models\PersonAccountLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Dossier annonceur (ADR-0010 §3). Le représentant est toujours une
 * liaison personne-compte réelle : un annonceur n'agit jamais via un
 * compte partagé (ADR-0004 §3.2).
 *
 * @property string $id
 * @property string $legal_name
 * @property string|null $legal_identifier
 * @property string $country_code
 * @property string $representative_person_account_link_id
 * @property array<int, mixed> $licenses
 * @property array<int, mixed> $territories
 * @property AdvertiserProfileStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class AdvertiserProfile extends Model
{
    protected $table = 'advertising.advertiser_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'legal_name', 'legal_identifier', 'country_code',
        'representative_person_account_link_id', 'licenses', 'territories', 'status',
    ];

    protected function casts(): array
    {
        return [
            'licenses' => 'array',
            'territories' => 'array',
            'status' => AdvertiserProfileStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            $profile->id ??= (string) Str::uuid7();
            $profile->licenses ??= [];
            $profile->territories ??= [];
        });
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'representative_person_account_link_id');
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'advertiser_profile_id');
    }
}
