<?php

namespace App\Modules\Alerts\Models;

use App\Modules\Alerts\Enums\CaseCategory;
use App\Modules\Alerts\Enums\DispatchState;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Transmission d'un dossier à une organisation affiliée
 * (ecosystem/alertes/03 §1). Chaque transmission est un enregistrement
 * distinct du dossier source ; un doublon actif vers la même organisation
 * est empêché par `institution_dispatches_one_active_per_org`.
 *
 * @property string $id
 * @property string $case_id
 * @property string $organization_id
 * @property string|null $territory_code
 * @property CaseCategory $category
 * @property DispatchState $state
 * @property string $channel
 * @property string $correlation_id
 * @property string|null $responsible_person_account_link_id
 * @property Carbon|CarbonImmutable|null $transmitted_at
 * @property Carbon|CarbonImmutable|null $received_at
 * @property Carbon|CarbonImmutable|null $accepted_at
 * @property Carbon|CarbonImmutable|null $processing_at
 * @property Carbon|CarbonImmutable|null $resolved_at
 * @property string|null $error_detail
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InstitutionDispatch extends Model
{
    protected $table = 'alerts.institution_dispatches';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'case_id', 'organization_id', 'territory_code', 'category', 'state', 'channel',
        'correlation_id', 'responsible_person_account_link_id',
        'transmitted_at', 'received_at', 'accepted_at', 'processing_at', 'resolved_at', 'error_detail',
    ];

    protected function casts(): array
    {
        return [
            'category' => CaseCategory::class,
            'state' => DispatchState::class,
            'transmitted_at' => 'datetime',
            'received_at' => 'datetime',
            'accepted_at' => 'datetime',
            'processing_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $dispatch): void {
            $dispatch->id ??= (string) Str::uuid7();
            $dispatch->state ??= DispatchState::Created;
            $dispatch->channel ??= 'in_app_portal';
        });
    }

    /**
     * @return BelongsTo<AlertCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(AlertCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'responsible_person_account_link_id');
    }
}
