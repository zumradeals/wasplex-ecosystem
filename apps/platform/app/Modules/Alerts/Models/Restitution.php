<?php

namespace App\Modules\Alerts\Models;

use App\Modules\Alerts\Enums\RestitutionState;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Remise sécurisée d'un bien retrouvé (ecosystem/institutions/01 §8). Le
 * code est stocké sous forme de condensat (`code_hash`), jamais en clair.
 * Remise et réception sont deux confirmations distinctes ; le témoin
 * facultatif ne reçoit aucune capacité (ne devient pas un acteur Wasplex).
 *
 * @property string $id
 * @property string $case_id
 * @property string|null $correspondence_report_id
 * @property string|null $organization_id
 * @property RestitutionState $state
 * @property string|null $code_hash
 * @property Carbon|CarbonImmutable|null $code_expires_at
 * @property Carbon|CarbonImmutable|null $delivered_at
 * @property string|null $delivered_confirmed_by_person_account_link_id
 * @property Carbon|CarbonImmutable|null $received_at
 * @property string|null $received_confirmed_by_person_account_link_id
 * @property string|null $witness_person_account_link_id
 * @property string|null $dispute_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Restitution extends Model
{
    protected $table = 'alerts.restitutions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'case_id', 'correspondence_report_id', 'organization_id', 'state',
        'code_hash', 'code_expires_at',
        'delivered_at', 'delivered_confirmed_by_person_account_link_id',
        'received_at', 'received_confirmed_by_person_account_link_id',
        'witness_person_account_link_id', 'dispute_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => RestitutionState::class,
            'code_expires_at' => 'datetime',
            'delivered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $restitution): void {
            $restitution->id ??= (string) Str::uuid7();
            $restitution->state ??= RestitutionState::Pending;
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
     * @return BelongsTo<CorrespondenceReport, $this>
     */
    public function correspondenceReport(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceReport::class, 'correspondence_report_id');
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
    public function deliveredConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'delivered_confirmed_by_person_account_link_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function receivedConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'received_confirmed_by_person_account_link_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function witness(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'witness_person_account_link_id');
    }
}
