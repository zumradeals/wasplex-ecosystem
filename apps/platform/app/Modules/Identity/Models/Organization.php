<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\OrganizationCategory;
use App\Modules\Identity\Enums\OrganizationState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Organisation enregistrable : wasplex, advertiser ou institution (Constitution article 7).
 *
 * L'affiliation institutionnelle complète, les campagnes et les contrats
 * commerciaux ne sont pas construits ici (hors périmètre P003-A).
 *
 * @property string $id
 * @property OrganizationCategory $category
 * @property string $legal_name
 * @property string $display_name
 * @property string|null $country_code
 * @property OrganizationState $state
 * @property Carbon|CarbonImmutable $effective_from
 * @property Carbon|CarbonImmutable|null $effective_to
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Organization extends Model
{
    protected $table = 'identity.organizations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'category' => OrganizationCategory::class,
            'state' => OrganizationState::class,
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $organization): void {
            $organization->id ??= (string) Str::uuid7();
            $organization->effective_from ??= now();
            $organization->state ??= OrganizationState::Draft;
        });
    }
}
