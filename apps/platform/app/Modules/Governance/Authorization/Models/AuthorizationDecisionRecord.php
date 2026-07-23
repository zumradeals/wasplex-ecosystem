<?php

namespace App\Modules\Governance\Authorization\Models;

use App\Modules\Governance\Authorization\Enums\AuthorizationDecision;
use App\Modules\Governance\Authorization\Enums\Operation;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\PersonAccountLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Journal append-only des décisions d'autorisation (P003-B1 §17). PostgreSQL
 * refuse UPDATE et DELETE sur cette table (déclencheurs). Ne contient jamais
 * de secret, OTP, document KYC, donnée médicale, profil publicitaire ou
 * payload métier complet.
 *
 * @property string $id
 * @property string $correlation_id
 * @property string $person_account_link_id
 * @property string|null $membership_id
 * @property string|null $organization_id
 * @property string $capability_key
 * @property int|null $capability_version
 * @property string|null $purpose_key
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property Operation $operation
 * @property AuthorizationDecision $decision
 * @property string $reason_code
 * @property string|null $policy_key
 * @property int|null $policy_version
 * @property array<string, mixed>|null $obligations
 * @property Carbon|CarbonImmutable $occurred_at
 * @property Carbon $created_at
 */
class AuthorizationDecisionRecord extends Model
{
    protected $table = 'governance.authorization_decisions';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'correlation_id', 'person_account_link_id', 'membership_id', 'organization_id',
        'capability_key', 'capability_version', 'purpose_key', 'resource_type', 'resource_id',
        'operation', 'decision', 'reason_code', 'policy_key', 'policy_version',
        'obligations', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'capability_version' => 'integer',
            'operation' => Operation::class,
            'decision' => AuthorizationDecision::class,
            'policy_version' => 'integer',
            'obligations' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            $record->id ??= (string) Str::uuid7();
            $record->occurred_at ??= now();
        });
    }

    /**
     * @return BelongsTo<PersonAccountLink, $this>
     */
    public function personAccountLink(): BelongsTo
    {
        return $this->belongsTo(PersonAccountLink::class, 'person_account_link_id');
    }

    /**
     * @return BelongsTo<Membership, $this>
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
