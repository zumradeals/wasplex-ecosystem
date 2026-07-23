<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\GrantEventType;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Journal append-only des événements de grant (P003-B1 §13, AMD-0012 §16).
 * PostgreSQL refuse UPDATE et DELETE sur cette table (déclencheurs).
 *
 * @property string $id
 * @property string $grant_id
 * @property string $actor_person_account_link_id
 * @property string|null $organization_id
 * @property GrantEventType $event_type
 * @property string|null $reason
 * @property string $policy_version_id
 * @property string $correlation_id
 * @property Carbon|CarbonImmutable $occurred_at
 * @property Carbon $created_at
 */
class GrantEvent extends Model
{
    protected $table = 'governance.grant_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'grant_id', 'actor_person_account_link_id', 'organization_id',
        'event_type', 'reason', 'policy_version_id', 'correlation_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => GrantEventType::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->id ??= (string) Str::uuid7();
            $event->occurred_at ??= now();
        });
    }

    /**
     * @return BelongsTo<Grant, $this>
     */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class, 'grant_id');
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'actor_person_account_link_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<PolicyVersion, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }
}
